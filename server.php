<?php

/**
 * WEBSOCKET SERVER EXAMPLE
 *
 */

$host = '127.0.0.1';
$port = 12344;

// Create a TCP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Bind the socket to the IP address and port
socket_bind($socket, $host, $port);

// Listen for incoming connections
socket_listen($socket);

echo "WebSocket server started on ws://$host:$port\n";

$clients = []; // Array to store connected clients

while (true) {
    //The script will essentially be "sleeping" at that point, waiting for a connection
    // Accept incoming client connections
    $client = socket_accept($socket);
    echo "Client connected\n";
    // Perform WebSocket handshake
    performHandshake($client);

    // Store client in the clients array
    $clients[] = $client;

    // Client communication loop
    while (true) {
        echo "\n second loop2 \n";
        // Read the HTTP request from the client
        $data = socket_read($client, 2048, PHP_BINARY_READ);
        // Handle client disconnection
        if ($data === false || trim($data) == 'exit') {
            echo "Client disconnected\n";
            disconnectClient($client, $clients);
            break;
        }

        if ($data) {
            broadcastMessage($clients, $data);
        }
    }
}

function disconnectClient($client, &$clients): void
{
    $key = array_search($client, $clients);
    socket_close($client);
    unset($clients[$key]);
}

function broadcastMessage(array $clients, $data)
{
    foreach ($clients as $client) {
        echo $decodedData = "\n" . decodeWebSocketFrame($data);
        $encodedMessage = encodeWebSocketFrame("Server: $decodedData");
        socket_write($client, $encodedMessage, strlen($encodedMessage));
    }
}
// Perform WebSocket handshake with the client
function performHandshake($client)
{
    // Read the HTTP request from the client
    $request = socket_read($client, 2048);
    // Check if the request contains the WebSocket upgrade header
    if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
        $key = base64_encode(sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        // Send the WebSocket handshake response
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $key\r\n\r\n";

        socket_write($client, $response);
    }
}


function decodeWebSocketFrame($data)
{
    $length = ord($data[1]) & 127;
    if ($length === 126) {
        $masks = substr($data, 4, 4);
        $data = substr($data, 8);
    } elseif ($length === 127) {
        $masks = substr($data, 10, 4);
        $data = substr($data, 14);
    } else {
        $masks = substr($data, 2, 4);
        $data = substr($data, 6);
    }
    $decoded = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $decoded .= $data[$i] ^ $masks[$i % 4];
    }
    return $decoded;
}

// Encode a WebSocket frame (for text frames)
function encodeWebSocketFrame($data)
{
    $length = strlen($data);
    $header = [];
    $header[0] = 129; // 0x1 text frame (FIN + opcode)
    if ($length <= 125) {
        $header[1] = $length;
    } elseif ($length <= 65535) {
        $header[1] = 126;
        $header[2] = ($length >> 8) & 255;
        $header[3] = $length & 255;
    } else {
        $header[1] = 127;
        $header[2] = ($length >> 56) & 255;
        $header[3] = ($length >> 48) & 255;
        $header[4] = ($length >> 40) & 255;
        $header[5] = ($length >> 32) & 255;
        $header[6] = ($length >> 24) & 255;
        $header[7] = ($length >> 16) & 255;
        $header[8] = ($length >> 8) & 255;
        $header[9] = $length & 255;
    }
    return implode(array_map("chr", $header)) . $data;
}
