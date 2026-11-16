@echo off
cd /d %~dp0
curl -s http://127.0.0.1:11434 >nul 2>&1 || start "Ollama" cmd /k ollama serve
start "Backend :8000" cmd /k "cd /d %~dp0backend && php artisan serve"
start "Frontend :5173" cmd /k "cd /d %~dp0frontend && npm run dev"
timeout /t 4 >nul
start http://localhost:5173
