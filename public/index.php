<?php
declare(strict_types=1);
session_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timify</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/timify-mark.svg">
    <link rel="apple-touch-icon" href="assets/images/timify-mark.svg">
    <link rel="stylesheet" href="assets/styles.css?v=20260629-entry-layout">
</head>
<body>
    <main class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <img class="brand-mark" src="assets/images/timify-mark.svg" alt="Timify">
                <strong>Timify</strong>
            </div>
            <nav class="nav">
                <button class="nav-item active" data-view="tracker" type="button">Time Tracker</button>
                <button class="nav-item" data-view="projects" type="button">Projects</button>
                <button class="nav-item" data-view="reports" type="button">Reports</button>
            </nav>
            <button class="ghost-button logout-button" type="button">Logout</button>
        </aside>

        <section class="workspace">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Workspace</p>
                    <h1 class="view-title">Time Tracker</h1>
                </div>
                <div class="user-pill" id="userPill"></div>
            </header>

            <section class="auth-panel" id="authPanel">
                <div class="auth-copy">
                    <p class="eyebrow">Welcome</p>
                    <h1>Track focused work by project.</h1>
                </div>
                <form class="auth-form" id="authForm">
                    <div class="auth-tabs">
                        <button class="auth-tab active" data-mode="login" type="button">Login</button>
                        <button class="auth-tab" data-mode="register" type="button">Register</button>
                    </div>
                    <label class="register-only">
                        Name
                        <input name="name" autocomplete="name">
                    </label>
                    <label>
                        Email
                        <input name="email" type="email" autocomplete="email" required>
                    </label>
                    <label>
                        Password
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <button class="primary-button" type="submit">Continue</button>
                    <p class="form-message" id="authMessage"></p>
                </form>
            </section>

            <div class="app-content hidden" id="appContent">
                <section class="view active" id="trackerView">
                    <form class="timer-bar" id="timerForm">
                        <input id="descriptionInput" maxlength="500" placeholder="What are you working on?">
                        <select id="projectSelect" required></select>
                        <strong class="timer-display" id="timerDisplay">00:00:00</strong>
                        <button class="primary-button timer-button" type="submit" id="timerButton">Start</button>
                    </form>
                    <div class="section-heading">
                        <h2>Recent Entries</h2>
                        <span id="weekTotal">Week total: 00:00:00</span>
                    </div>
                    <div class="entries-list" id="entriesList"></div>
                </section>

                <section class="view" id="projectsView">
                    <form class="project-form" id="projectForm">
                        <input name="name" maxlength="120" placeholder="Project name" required>
                        <input name="color" type="color" value="#2563eb" aria-label="Project color">
                        <button class="primary-button" type="submit">Add Project</button>
                    </form>
                    <div class="project-list" id="projectList"></div>
                </section>

                <section class="view" id="reportsView">
                    <div class="report-grid">
                        <div class="chart-panel">
                            <canvas id="reportChart" width="900" height="360"></canvas>
                        </div>
                        <div class="totals-panel" id="reportTotals"></div>
                    </div>
                </section>
            </div>
        </section>
    </main>
    <script src="assets/app.js?v=20260629-entry-edit"></script>
</body>
</html>
