<?php
function getDB()
{
    // MySQLi connection — can be overridden via environment variables.
    $dbHost = getenv('DTI_DB_HOST') ?: '127.0.0.1';
    $dbUser = getenv('DTI_DB_USER') ?: 'root';
    $dbPass = getenv('DTI_DB_PASS');
    if ($dbPass === false) {
        $dbPass = '';
    }

    // Preferred DB can be overridden; fallback keeps compatibility with older installs.
    $preferredDb = getenv('DTI_DB_NAME') ?: 'dti_nv';
    $dbCandidates = array_values(array_unique([$preferredDb, 'dti_nv', 'dti']));

    // Connect to MySQL server
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass);
    if ($mysqli->connect_errno) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }

    // Prefer an existing populated DB so previously-created accounts continue to work.
    $existingDb = null;
    $bestUserCount = -1;
    foreach ($dbCandidates as $candidate) {
        $safe = $mysqli->real_escape_string($candidate);
        $dbExistsRes = $mysqli->query("SHOW DATABASES LIKE '" . $safe . "'");
        if (!$dbExistsRes || $dbExistsRes->num_rows === 0) {
            continue;
        }

        if (!$mysqli->select_db($candidate)) {
            continue;
        }

        $usersTableRes = $mysqli->query("SHOW TABLES LIKE 'users'");
        if ($usersTableRes && $usersTableRes->num_rows > 0) {
            $countRes = $mysqli->query("SELECT COUNT(*) AS c FROM users");
            $userCount = 0;
            if ($countRes) {
                $row = $countRes->fetch_assoc();
                $userCount = (int)($row['c'] ?? 0);
            }

            if ($userCount > $bestUserCount) {
                $bestUserCount = $userCount;
                $existingDb = $candidate;
            }
        }
    }

    if ($existingDb === null) {
        $dbName = $preferredDb;
        $create = $mysqli->query("CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if ($create === false) {
            throw new Exception('Failed to create database: ' . $mysqli->error);
        }

        if (!$mysqli->select_db($dbName)) {
            throw new Exception('Failed to select database: ' . $mysqli->error);
        }
    } else {
        if (!$mysqli->select_db($existingDb)) {
            throw new Exception('Failed to select database: ' . $mysqli->error);
        }
    }

    // Ensure proper charset
    if (! $mysqli->set_charset('utf8mb4')) {
        // not fatal, but warn
        trigger_error('Failed to set charset: ' . $mysqli->error, E_USER_WARNING);
    }

    return $mysqli;
}

