<?php
// Enable CORS for API endpoints
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Store logs in memory (in a real app, use a database or file)
$logsFile = 'logs.json';
if (!file_exists($logsFile)) file_put_contents($logsFile, json_encode([]));

// Handle API routes
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if ($requestUri === '/api/logs' && $method === 'GET') {
    // Return logs as JSON
    header('Content-Type: application/json');
    echo file_get_contents($logsFile);
    exit;
}

if ($requestUri === '/iclock/cdata' && $method === 'POST') {
    $rawInput = file_get_contents('php://input');
    file_put_contents('php://stderr', "\n📥 RAW PUSH: $rawInput\n", FILE_APPEND);

    $lines = explode("\n", trim($rawInput));
    $logs = json_decode(file_get_contents($logsFile), true);

    foreach ($lines as $line) {
        $parts = explode("\t", trim($line));

        if (stripos($parts[0], 'OPLOG') === 0 || count($parts) < 3) {
            error_log("Ignored: $line");
            continue;
        }

        $userId = $parts[0];
        $statusCode = $parts[2];

        if ($statusCode !== '0' && $statusCode !== '1') {
            continue;
        }

        $dateTime = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        $time = $dateTime->format('h:i:s A');
        $date = $dateTime->format('d/m/Y');
        $status = $statusCode === '0' ? 'Check-In' : 'Check-Out';

        $logs[] = [
            'userId' => $userId,
            'status' => $status,
            'time' => $time,
            'date' => $date
        ];

        if (count($logs) > 50) {
            array_shift($logs);
        }
    }

    file_put_contents($logsFile, json_encode($logs));
    echo "OK";
    exit;
}

// Serve dashboard HTML (if root is visited)
if ($requestUri === '/') {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ZKTeco Attendance Dashboard</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      padding: 10px;
      margin: 0;
    }
    h1 {
      text-align: center;
      color: #333;
      margin-bottom: 20px;
    }
    .container {
      max-width: 1000px;
      margin: auto;
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      min-width: 600px;
    }
    th, td {
      padding: 12px 16px;
      border: 1px solid #ddd;
      text-align: center;
    }
    th {
      background-color: red;
      color: white;
    }
    tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    #refresh {
      margin: 10px auto 20px auto;
      display: block;
      padding: 10px 20px;
      background: #4CAF50;
      color: white;
      border: none;
      font-size: 16px;
      cursor: pointer;
      border-radius: 4px;
    }
    @media (max-width: 600px) {
      table {
        font-size: 14px;
        min-width: 100%;
      }
      th, td {
        padding: 10px;
      }
    }
  </style>
</head>
<body>
  <h1>ZKTeco Live Attendance Logs</h1>
  <div class="container">
    <table>
      <thead>
        <tr>
          <th>User ID</th>
          <th>Status</th>
          <th>Time</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="logTable"></tbody>
    </table>
  </div>

  <script>
    async function fetchLogs() {
      const res = await fetch("/api/logs");
      const data = await res.json();
      const table = document.getElementById("logTable");
      table.innerHTML = "";
      data.slice().reverse().forEach(log => {
        const row = document.createElement("tr");
        row.innerHTML = `
          <td>${log.userId}</td>
          <td>${log.status}</td>
          <td>${log.time}</td>
          <td>${log.date}</td>
        `;
        table.appendChild(row);
      });
    }
    setInterval(fetchLogs, 10000);
    fetchLogs();
  </script>
</body>
</html>';
    exit;
}

// Fallback for unknown routes
http_response_code(404);
echo "Not Found";
