# CODE://MASTER（前後端分離版）

結構：backend\（Laravel API，:8000）、frontend\（Vue + Tailwind，:5173）

1. Laragon Start All（MySQL 要啟動）；安裝 Ollama 並 `ollama pull qwen2.5-coder:14b`
2. 在本資料夾開 Laragon Terminal：`powershell -ExecutionPolicy Bypass -File .\setup.ps1`
3. 雙擊 `start-dev.bat`，開啟 http://localhost:5173

## 常見問題
- 狀態「離線」：Ollama 沒開，執行 `ollama serve`
- 歷史紀錄「未啟用」：MySQL 沒啟動，或 backend\.env 的 DB_* 不對；改完在 backend 執行 `php artisan config:clear`
- 檢查 API：在 backend 執行 `php artisan route:list`，應看到 api/status、api/analyze
- 逾時：backend\.env 加 `OLLAMA_TIMEOUT=600`
- 顯存不足：`OLLAMA_MODEL=qwen2.5-coder:7b`（先 pull）

## Git
git add . && git commit -m "init" && git branch -M main && git remote add origin <repo網址> && git push -u origin main
