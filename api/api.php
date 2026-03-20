<?php
// ============================================================
//  GeoUTC — api/api.php
//  Punto de entrada único para todos los endpoints
//  Uso: api/api.php?action=<nombre>
// ============================================================

require_once __DIR__ . '/../config/conexion.php';

// ── CORS (útil en desarrollo local) ─────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helper ───────────────────────────────────────────────────
function resp(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── Router ───────────────────────────────────────────────────
// IMPORTANTE: initUser() ya fue llamado al final de conexion.php,
// por lo que $_SESSION['user_id'] ya existe en este punto.
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$uid    = (int)($_SESSION['user_id'] ?? initUser());

switch ($action) {

    // ── GET /api/api.php?action=lugares ──────────────────────
    //  Devuelve todos los lugares + cuáles visitó el usuario
    case 'lugares':
        $lugares   = getLugares();
        $visitados = array_column(getLugaresVisitados($uid), 'id');
        resp([
            'lugares'   => $lugares,
            'visitados' => $visitados,
        ]);

    // ── GET /api/api.php?action=usuario ──────────────────────
    //  Datos del usuario actual (puntos, progreso, nombre)
    case 'usuario':
        $user = getUsuario($uid);
        resp([
            'id'       => $uid,
            'nombre'   => $user['nombre'],
            'puntos'   => (int)$user['puntos'],
            'progreso' => getProgreso($uid),
        ]);

    // ── GET /api/api.php?action=misiones ─────────────────────
    //  Misiones activas con estado completado/pendiente
    case 'misiones':
        resp(['misiones' => getMisiones($uid)]);

    // ── POST /api/api.php?action=visitar ─────────────────────
    //  Body JSON: { "lugar_id": 3 }
    case 'visitar':
        $body    = bodyJson();
        $lugar_id = (int)($body['lugar_id'] ?? $_GET['lugar_id'] ?? 0);
        if (!$lugar_id) resp(['success' => false, 'message' => 'lugar_id requerido'], 400);
        resp(registrarVisita($uid, $lugar_id));

    // ── GET /api/api.php?action=recompensas ──────────────────
    //  Lista de recompensas disponibles en la tienda
    case 'recompensas':
        $user        = getUsuario($uid);
        $recompensas = getRecompensas();
        resp([
            'recompensas'  => $recompensas,
            'puntos_usuario'=> (int)$user['puntos'],
        ]);

    // ── POST /api/api.php?action=canjear ─────────────────────
    //  Body JSON: { "recompensa_id": 2 }
    case 'canjear':
        $body         = bodyJson();
        $recompensa_id = (int)($body['recompensa_id'] ?? $_GET['recompensa_id'] ?? 0);
        if (!$recompensa_id) resp(['success' => false, 'mensaje' => 'recompensa_id requerido'], 400);
        resp(canjearRecompensa($uid, $recompensa_id));

    // ── GET /api/api.php?action=mis-recompensas ──────────────
    //  Recompensas ya canjeadas por el usuario
    case 'mis-recompensas':
        resp(['canjeadas' => getRecompensasCanjeadas($uid)]);

    // ── GET /api/api.php?action=comentarios ──────────────────
    //  Comentarios recientes (query param: limite=20)
    case 'comentarios':
        $limite = min((int)($_GET['limite'] ?? 20), 100);
        resp([
            'comentarios' => getComentarios($limite),
            'lugares'     => getLugares(),
        ]);

    // ── POST /api/api.php?action=comentar ────────────────────
    //  Body JSON: { "lugar_id": 1, "texto": "Muy bonito" }
    case 'comentar':
        $body     = bodyJson();
        $lugar_id = (int)($body['lugar_id'] ?? 0);
        $texto    = trim($body['texto'] ?? '');
        if (!$lugar_id || !$texto) {
            resp(['success' => false, 'mensaje' => 'lugar_id y texto son requeridos'], 400);
        }
        $id = guardarComentario($uid, $lugar_id, $texto);
        resp(['success' => true, 'id' => $id]);

    // ── Acción desconocida ───────────────────────────────────
    default:
        resp([
            'error'    => 'Acción no reconocida',
            'acciones' => [
                'GET  lugares',
                'GET  usuario',
                'GET  misiones',
                'POST visitar       { lugar_id }',
                'GET  recompensas',
                'POST canjear       { recompensa_id }',
                'GET  mis-recompensas',
                'GET  comentarios   [limite]',
                'POST comentar      { lugar_id, texto }',
            ],
        ], 404);
}
