<?php
// ============================================================
//  GeoUTC — config/conexion.php
//  Conexión PDO + todas las funciones de negocio
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Configuración ────────────────────────────────────────────
$host        = 'localhost';
$usuario     = 'root';
$password    = '';
$base_datos  = 'tour_utc';

try {
    // 1. Conectar sin BD para crearla si no existe
    $tmp = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $password);
    $tmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tmp->exec("CREATE DATABASE IF NOT EXISTS `$base_datos`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tmp = null;

    // 2. Conectar a la BD
    $db = new PDO(
        "mysql:host=$host;dbname=$base_datos;charset=utf8mb4",
        $usuario, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // 3. Crear tablas si no existen
    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            nombre          VARCHAR(100) NOT NULL,
            email           VARCHAR(120) NOT NULL UNIQUE,
            session_id      VARCHAR(255),
            puntos          INT DEFAULT 0,
            fecha_registro  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email   (email),
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS lugares (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            nombre            VARCHAR(100) NOT NULL,
            latitud           DECIMAL(10,8) NOT NULL,
            longitud          DECIMAL(11,8) NOT NULL,
            dato_curioso      TEXT,
            es_halcon         TINYINT(1) DEFAULT 0,
            puntos_recompensa INT DEFAULT 5,
            icono             VARCHAR(50) DEFAULT 'book-open-reader'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS visitas (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id    INT NOT NULL,
            lugar_id      INT NOT NULL,
            fecha_visita  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            puntos_ganados INT DEFAULT 0,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (lugar_id)   REFERENCES lugares(id)  ON DELETE CASCADE,
            UNIQUE KEY visita_unica (usuario_id, lugar_id),
            INDEX idx_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS comentarios (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id  INT NOT NULL,
            lugar_id    INT NOT NULL,
            texto       TEXT NOT NULL,
            fecha       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (lugar_id)   REFERENCES lugares(id)  ON DELETE CASCADE,
            INDEX idx_fecha (fecha DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS misiones (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            codigo            VARCHAR(50) UNIQUE NOT NULL,
            nombre            VARCHAR(100) NOT NULL,
            descripcion       TEXT,
            recompensa_puntos INT DEFAULT 0,
            tipo              VARCHAR(50),
            objetivo          INT,
            activa            TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS misiones_completadas (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id       INT NOT NULL,
            mision_id        INT NOT NULL,
            fecha_completado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (mision_id)  REFERENCES misiones(id)  ON DELETE CASCADE,
            UNIQUE KEY mision_usuario (usuario_id, mision_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS recompensas (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            codigo       VARCHAR(50) UNIQUE NOT NULL,
            nombre       VARCHAR(100) NOT NULL,
            descripcion  TEXT,
            costo_puntos INT NOT NULL,
            stock        INT DEFAULT 0,
            activa       TINYINT(1) DEFAULT 1,
            icono        VARCHAR(50) DEFAULT 'gift'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS recompensas_canjeadas (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id   INT NOT NULL,
            recompensa_id INT NOT NULL,
            codigo_canje VARCHAR(20) UNIQUE,
            fecha_canje  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usado        TINYINT(1) DEFAULT 0,
            fecha_uso    TIMESTAMP NULL,
            FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE CASCADE,
            FOREIGN KEY (recompensa_id) REFERENCES recompensas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Seed: lugares
    $count = $db->query("SELECT COUNT(*) FROM lugares")->fetchColumn();
    if ($count == 0) {
        $db->exec("
            INSERT INTO lugares (nombre, latitud, longitud, dato_curioso, es_halcon, puntos_recompensa, icono) VALUES
            ('Vinculación',    25.557867, -100.936501, 'Sede administrativa de los Halcones UTC.', 1, 10, 'hawk'),
            ('Dirección',      25.557386, -100.935864, 'Aquí se toman las decisiones más importantes del campus.', 1, 10, 'hawk'),
            ('Centro de Idiomas', 25.557346, -100.936921, 'Abierta 24/7 durante períodos de exámenes.', 0, 5, 'book-open-reader'),
            ('Edificio 4',     25.556381, -100.936799, 'Centro de innovación y emprendimiento estudiantil.', 0, 5, 'building'),
            ('Edificio 3',     25.555889, -100.935752, 'Centro de investigación y desarrollo tecnológico.', 0, 5, 'building'),
            ('Domo',           25.555303, -100.934878, 'El corazón de la UTC, donde se realizan los eventos más importantes.', 1, 10, 'hawk')
        ");
    }

    // 5. Seed: misiones
    $count = $db->query("SELECT COUNT(*) FROM misiones")->fetchColumn();
    if ($count == 0) {
        $db->exec("
            INSERT INTO misiones (codigo, nombre, descripcion, recompensa_puntos, tipo, objetivo) VALUES
            ('mision1', 'Recorrido Inicial',   'Visita 3 lugares Halcón',              30, 'visitas_halcon',    3),
            ('mision2', 'Explorador Novato',   'Visita todos los edificios académicos', 50, 'visitas_academicos', 3)
        ");
    }

    // 6. Seed: recompensas
    $count = $db->query("SELECT COUNT(*) FROM recompensas")->fetchColumn();
    if ($count == 0) {
        $db->exec("
            INSERT INTO recompensas (codigo, nombre, descripcion, costo_puntos, stock, icono) VALUES
            ('descuento_cafeteria', 'Descuento 20% Cafetería',       'Obtén 20% de descuento en la cafetería por una semana', 50,  20, 'coffee'),
            ('entrada_gratis',      'Entrada Gratis Evento Deportivo','Entrada gratuita a cualquier evento deportivo',          75,  15, 'running'),
            ('tour_vip',            'Tour VIP Campus',               'Recorrido exclusivo con un guía personal',               100, 10, 'map-marked-alt')
        ");
    }

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB error: ' . $e->getMessage()]));
}


// ============================================================
//  FUNCIONES
// ============================================================

// ── Usuario ──────────────────────────────────────────────────

function initUser(): int {
    global $db;
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];

    $sid = session_id();
    $u   = $db->prepare("SELECT id FROM usuarios WHERE session_id = ?");
    $u->execute([$sid]);
    $row = $u->fetch();

    if ($row) {
        $_SESSION['user_id'] = $row['id'];
        return (int)$row['id'];
    }

    $name  = 'Usuario_' . substr($sid, 0, 8);
    $email = $sid . '@temp.utc';
    $ins   = $db->prepare("INSERT INTO usuarios (nombre, email, session_id) VALUES (?, ?, ?)");
    $ins->execute([$name, $email, $sid]);
    $_SESSION['user_id'] = (int)$db->lastInsertId();
    return $_SESSION['user_id'];
}

function getUsuario(int $uid): array {
    global $db;
    $s = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $s->execute([$uid]);
    return $s->fetch() ?: [];
}

function sumarPuntos(int $uid, int $pts): int {
    global $db;
    $db->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$pts, $uid]);
    return (int)$db->query("SELECT puntos FROM usuarios WHERE id = $uid")->fetchColumn();
}

function restarPuntos(int $uid, int $pts): void {
    global $db;
    $db->prepare("UPDATE usuarios SET puntos = puntos - ? WHERE id = ?")->execute([$pts, $uid]);
}

// ── Lugares ──────────────────────────────────────────────────

function getLugares(): array {
    global $db;
    return $db->query("SELECT * FROM lugares ORDER BY id")->fetchAll();
}

function getLugarById(int $id): array {
    global $db;
    $s = $db->prepare("SELECT * FROM lugares WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: [];
}

function getLugaresVisitados(int $uid): array {
    global $db;
    $s = $db->prepare("
        SELECT l.* FROM lugares l
        INNER JOIN visitas v ON l.id = v.lugar_id
        WHERE v.usuario_id = ?
    ");
    $s->execute([$uid]);
    return $s->fetchAll();
}

function yaVisitado(int $uid, int $lid): bool {
    global $db;
    $s = $db->prepare("SELECT COUNT(*) FROM visitas WHERE usuario_id = ? AND lugar_id = ?");
    $s->execute([$uid, $lid]);
    return $s->fetchColumn() > 0;
}

function getProgreso(int $uid): int {
    global $db;
    $total    = (int)$db->query("SELECT COUNT(*) FROM lugares")->fetchColumn();
    $s        = $db->prepare("SELECT COUNT(*) FROM visitas WHERE usuario_id = ?");
    $s->execute([$uid]);
    $visitados = (int)$s->fetchColumn();
    return $total > 0 ? (int)round($visitados / $total * 100) : 0;
}

// ── Visitas ──────────────────────────────────────────────────

function registrarVisita(int $uid, int $lid): array {
    global $db;

    if (yaVisitado($uid, $lid)) {
        return ['success' => false, 'message' => 'Ya visitaste este lugar'];
    }

    $lugar = getLugarById($lid);
    if (!$lugar) return ['success' => false, 'message' => 'Lugar no encontrado'];

    $pts = (int)$lugar['puntos_recompensa'];
    $db->prepare("INSERT INTO visitas (usuario_id, lugar_id, puntos_ganados) VALUES (?, ?, ?)")
       ->execute([$uid, $lid, $pts]);

    $total = sumarPuntos($uid, $pts);
    $misiones = verificarMisiones($uid);

    return [
        'success'             => true,
        'puntos'              => $pts,
        'total_puntos'        => $total,
        'misiones_completadas'=> $misiones,
    ];
}

// ── Misiones ─────────────────────────────────────────────────

function getMisiones(int $uid): array {
    global $db;
    $s = $db->prepare("
        SELECT m.*, mc.id AS completada_id, mc.fecha_completado
        FROM misiones m
        LEFT JOIN misiones_completadas mc ON m.id = mc.mision_id AND mc.usuario_id = ?
        WHERE m.activa = 1
        ORDER BY m.id
    ");
    $s->execute([$uid]);
    return $s->fetchAll();
}

function verificarMisiones(int $uid): array {
    global $db;
    $completadas = [];

    // Misiones activas no completadas aún
    $s = $db->prepare("
        SELECT m.* FROM misiones m
        WHERE m.activa = 1
          AND m.id NOT IN (SELECT mision_id FROM misiones_completadas WHERE usuario_id = ?)
    ");
    $s->execute([$uid]);
    $pendientes = $s->fetchAll();

    foreach ($pendientes as $m) {
        $ok = false;

        switch ($m['tipo']) {
            case 'visitas_halcon':
                $q = $db->prepare("
                    SELECT COUNT(*) FROM visitas v
                    INNER JOIN lugares l ON v.lugar_id = l.id
                    WHERE v.usuario_id = ? AND l.es_halcon = 1
                ");
                $q->execute([$uid]);
                $ok = $q->fetchColumn() >= $m['objetivo'];
                break;

            case 'visitas_academicos':
                $q = $db->prepare("
                    SELECT COUNT(*) FROM visitas v
                    INNER JOIN lugares l ON v.lugar_id = l.id
                    WHERE v.usuario_id = ?
                      AND l.nombre IN ('Centro de Idiomas','Edificio 4','Edificio 3')
                ");
                $q->execute([$uid]);
                $ok = $q->fetchColumn() >= $m['objetivo'];
                break;
        }

        if ($ok) {
            try {
                $db->prepare("INSERT INTO misiones_completadas (usuario_id, mision_id) VALUES (?, ?)")
                   ->execute([$uid, $m['id']]);
                sumarPuntos($uid, (int)$m['recompensa_puntos']);
                $completadas[] = [
                    'nombre'    => $m['nombre'],
                    'recompensa'=> $m['recompensa_puntos'],
                    'descripcion'=> $m['descripcion'],
                ];
            } catch (PDOException) { /* ya existía */ }
        }
    }

    return $completadas;
}

// ── Recompensas ──────────────────────────────────────────────

function getRecompensas(): array {
    global $db;
    return $db->query("SELECT * FROM recompensas WHERE activa = 1 ORDER BY costo_puntos")->fetchAll();
}

function canjearRecompensa(int $uid, int $rid): array {
    global $db;
    try {
        $db->beginTransaction();

        $s = $db->prepare("SELECT * FROM recompensas WHERE id = ? AND activa = 1 FOR UPDATE");
        $s->execute([$rid]);
        $r = $s->fetch();

        if (!$r)             throw new Exception('Recompensa no disponible');
        if ($r['stock'] <= 0) throw new Exception('Recompensa agotada');

        $user = getUsuario($uid);
        if ($user['puntos'] < $r['costo_puntos']) throw new Exception('Puntos insuficientes');

        $codigo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

        $db->prepare("INSERT INTO recompensas_canjeadas (usuario_id, recompensa_id, codigo_canje) VALUES (?, ?, ?)")
           ->execute([$uid, $rid, $codigo]);
        $db->prepare("UPDATE recompensas SET stock = stock - 1 WHERE id = ?")
           ->execute([$rid]);
        restarPuntos($uid, (int)$r['costo_puntos']);

        $db->commit();
        return ['success' => true, 'codigo_canje' => $codigo, 'mensaje' => 'Recompensa canjeada exitosamente'];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'mensaje' => $e->getMessage()];
    }
}

function getRecompensasCanjeadas(int $uid): array {
    global $db;
    $s = $db->prepare("
        SELECT rc.*, r.nombre, r.descripcion, r.icono
        FROM recompensas_canjeadas rc
        INNER JOIN recompensas r ON rc.recompensa_id = r.id
        WHERE rc.usuario_id = ?
        ORDER BY rc.fecha_canje DESC
    ");
    $s->execute([$uid]);
    return $s->fetchAll();
}

// ── Comentarios ──────────────────────────────────────────────

function guardarComentario(int $uid, int $lid, string $texto): int {
    global $db;
    $db->prepare("INSERT INTO comentarios (usuario_id, lugar_id, texto) VALUES (?, ?, ?)")
       ->execute([$uid, $lid, $texto]);
    return (int)$db->lastInsertId();
}

function getComentarios(int $limite = 20): array {
    global $db;
    $limite = (int)$limite; // cast seguro para interpolación directa
    $s = $db->query("
        SELECT c.*, u.nombre AS usuario_nombre, l.nombre AS lugar_nombre
        FROM comentarios c
        INNER JOIN usuarios u ON c.usuario_id = u.id
        INNER JOIN lugares  l ON c.lugar_id   = l.id
        ORDER BY c.fecha DESC
        LIMIT $limite
    ");
    return $s->fetchAll();
}

// ── Inicializar usuario al incluir ───────────────────────────
initUser();
