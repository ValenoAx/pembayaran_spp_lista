<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Pembayaran SPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2248%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2230%22%20y%3D%2220%22%20width%3D%2240%22%20height%3D%2260%22%20rx%3D%226%22%20fill%3D%22%23ffffff%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2230%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23DB2777%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2240%22%20width%3D%2226%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Crect%20x%3D%2237%22%20y%3D%2250%22%20width%3D%2218%22%20height%3D%224%22%20rx%3D%222%22%20fill%3D%22%23f9a8d4%22/%3E%3Ccircle%20cx%3D%2263%22%20cy%3D%2266%22%20r%3D%2212%22%20fill%3D%22%23DB2777%22/%3E%3Cpath%20d%3D%22M58%2066%20L61.5%2069.5%20L68.5%2062.5%22%20stroke%3D%22%23ffffff%22%20stroke-width%3D%223%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22/%3E%3C/svg%3E">

    <style>
        :root {
            --pastel-pink: #ffe6f0;
            --pastel-blue: #e3f2fd;
            --pastel-yellow: #fffde7;
            --soft-purple: #f3e5f5;
        }
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03) !important;
            transition: transform 0.2s ease;
        }
        .btn-primary {
            background-color: #89cff0;
            border: none;
            color: #1e293b;
            font-weight: 600;
            border-radius: 10px;
            padding: 8px 16px;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #6fb3d2;
            color: #fff;
        }
        .table {
            vertical-align: middle;
        }
        .table thead th {
            background-color: #fdf2f8 !important;
            color: #db2777;
            border-bottom: 2px solid #fbcfe8;
        }
        * {
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            cursor: default !important;
        }
        a, button, .btn, label, select, .form-select, .cek-bulan {
            cursor: pointer !important;
        }
        input[type="text"], input[type="number"], input[type="password"],
        input[type="email"], input[type="search"], textarea {
            user-select: text !important;
            cursor: text !important;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">