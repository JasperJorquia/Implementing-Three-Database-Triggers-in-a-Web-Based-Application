<?php

function detectQrPhoneOrigin(): string
{
    $laptopIp = null;
    $routeSocket = @stream_socket_client('udp://8.8.8.8:53', $socketErrorNumber, $socketError, 1, STREAM_CLIENT_CONNECT);
    if ($routeSocket) {
        $routeAddress = stream_socket_get_name($routeSocket, false);
        fclose($routeSocket);
        $routeIp = $routeAddress ? explode(':', $routeAddress)[0] : '';
        if (filter_var($routeIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && substr($routeIp, 0, 4) !== '127.' && substr($routeIp, 0, 8) !== '169.254.') {
            $laptopIp = $routeIp;
        }
    }

    $hostName = gethostname();
    $hostIps = $hostName ? gethostbynamel($hostName) : false;
    $ipCandidates = array_unique(array_merge(
        is_array($hostIps) ? $hostIps : [],
        [$_SERVER['SERVER_ADDR'] ?? '']
    ));
    $fallbackIp = null;
    foreach (($laptopIp ? [] : $ipCandidates) as $candidateIp) {
        if (!filter_var($candidateIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || substr($candidateIp, 0, 4) === '127.' || substr($candidateIp, 0, 8) === '169.254.') {
            continue;
        }

        $fallbackIp ??= $candidateIp;
        $ipNumber = ip2long($candidateIp);
        $isPrivate = ($ipNumber & 0xff000000) === 0x0a000000
            || ($ipNumber & 0xfff00000) === 0xac100000
            || ($ipNumber & 0xffff0000) === 0xc0a80000;
        if ($isPrivate) {
            $laptopIp = $candidateIp;
            break;
        }
    }
    $laptopIp ??= $fallbackIp;

    if (!$laptopIp) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
    $defaultPort = $scheme === 'https' ? 443 : 80;

    return $scheme . '://' . $laptopIp . ($port === $defaultPort ? '' : ':' . $port);
}
