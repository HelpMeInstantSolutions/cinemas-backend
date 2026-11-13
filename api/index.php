<?php
// backend/api/index.php - Dailymotion OAuth Callback & API Gateway
require_once __DIR__ . '/config.php';

// Handle 1-Click Dailymotion OAuth Redirect
if (!empty($_GET['code'])) {
    $code = trim($_GET['code']);
    $clientId = '20339e78eaa0487f12ea';
    $clientSecret = 'd416efc0eddc6d29e1900833176cbddb5373a1e3';
    
    // Candidate Redirect URIs registered in Dailymotion partner console
    $candidateUris = [
        'https://cinemas.mybhiwani.in/api/',
        'https://cinemas.mybhiwani.in/api'
    ];

    $tData = null;
    $lastErr = null;

    foreach ($candidateUris as $curUri) {
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $curUri,
            'code' => $code
        ]);

        $res = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);
        $parsed = $res ? json_decode($res, true) : null;
        if ($parsed && !empty($parsed['access_token']) && !empty($parsed['refresh_token'])) {
            $tData = $parsed;
            break;
        } else {
            $lastErr = $parsed;
        }
    }

    if ($tData && !empty($tData['access_token'])) {
        $accessToken = $tData['access_token'];
        $refreshToken = $tData['refresh_token'];
        $expiresAt = time() + (int)($tData['expires_in'] ?? 36000);
        $uid = $tData['uid'] ?? '';

        // Fetch channel info
        $uRes = httpGetDailymotion("https://api.dailymotion.com/me?fields=id,screenname,username,email", ["Authorization: Bearer " . $accessToken]);
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? $uData['username'] ?? 'Channel';
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        $accName = $channelName . ' (' . $uid . ')';

        // Check if exists
        $checkStmt = $pdo->prepare("SELECT id FROM dm_accounts WHERE uid = ? OR client_id = ?");
        $checkStmt->execute([$uid, $clientId]);
        $existingId = (int)$checkStmt->fetchColumn();

        if ($existingId > 0) {
            $stmt = $pdo->prepare("
                UPDATE dm_accounts 
                SET name = ?, channel_name = ?, uid = ?, email = ?, client_id = ?, client_secret = ?, refresh_token = ?, access_token = ?, expires_at = ?, status = 1, is_default = 1 
                WHERE id = ?
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt, $existingId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt]);
        }

        header("Location: /admin/dm_accounts/?connected=1&channel=" . urlencode($channelName));
        exit();
    } else {
        $errMsg = $lastErr['error_description'] ?? ($lastErr['error']['message'] ?? 'Token exchange failed');
        header("Location: /admin/dm_accounts/?error=" . urlencode($errMsg));
        exit();
    }
}

// Health check response
sendResponse(true, "Cinemas & Web Series API Gateway Active", [
    'service' => 'Cinemas OTT Engine',
    'status' => 'operational',
    'timestamp' => time()
]);
