
@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Ripristino MySQL XAMPP

REM =========================================================
REM CONFIGURAZIONE
REM Cambia questo percorso se XAMPP non e' in C:\xampp
REM =========================================================
set "XAMPP=C:\xampp"
set "MYSQL_BIN=%XAMPP%\mysql\bin"
set "MYSQL_DATA=%XAMPP%\mysql\data"
set "MYSQL_BACKUP=%XAMPP%\mysql\backup"

echo.
echo ========================================================
echo   RIPRISTINO MYSQL XAMPP
echo ========================================================
echo.

if not exist "%XAMPP%" (
    echo [ERRORE] Cartella XAMPP non trovata: %XAMPP%
    pause
    exit /b 1
)

if not exist "%MYSQL_DATA%" (
    echo [ERRORE] Cartella data non trovata: %MYSQL_DATA%
    pause
    exit /b 1
)

if not exist "%MYSQL_BACKUP%" (
    echo [ERRORE] Cartella backup non trovata: %MYSQL_BACKUP%
    pause
    exit /b 1
)

for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%I"
set "SAVE_DIR=%XAMPP%\mysql\data_RECOVERY_%STAMP%"

echo [1/6] Chiusura processi MySQL...
taskkill /F /IM mysqld.exe >nul 2>&1
taskkill /F /IM mysql.exe >nul 2>&1
timeout /t 2 /nobreak >nul

echo [2/6] Backup cartella data attuale...
mkdir "%SAVE_DIR%" >nul 2>&1
robocopy "%MYSQL_DATA%" "%SAVE_DIR%" /E /COPYALL /R:1 /W:1 >nul

if errorlevel 8 (
    echo [ERRORE] Backup fallito.
    echo Controlla permessi o file bloccati.
    pause
    exit /b 1
)

echo [3/6] Ricreazione cartella data da backup di XAMPP...
ren "%MYSQL_DATA%" "data_broken_%STAMP%" || (
    echo [ERRORE] Impossibile rinominare la cartella data.
    echo Chiudi XAMPP e riesegui questo file come amministratore.
    pause
    exit /b 1
)

mkdir "%MYSQL_DATA%" >nul 2>&1
robocopy "%MYSQL_BACKUP%" "%MYSQL_DATA%" /E /COPYALL /R:1 /W:1 >nul

if errorlevel 8 (
    echo [ERRORE] Copia del backup base fallita.
    pause
    exit /b 1
)

set "OLD_DATA=%XAMPP%\mysql\data_broken_%STAMP%"

echo [4/6] Copia dei database utente...
for /D %%D in ("%OLD_DATA%\*") do (
    set "DBNAME=%%~nxD"

    if /I not "!DBNAME!"=="mysql" ^
    if /I not "!DBNAME!"=="performance_schema" ^
    if /I not "!DBNAME!"=="phpmyadmin" ^
    if /I not "!DBNAME!"=="test" (
        echo     - Copio database: !DBNAME!
        robocopy "%%~fD" "%MYSQL_DATA%\!DBNAME!" /E /COPYALL /R:1 /W:1 >nul
    )
)

echo [5/6] Copia file InnoDB utili se presenti...
if exist "%OLD_DATA%\ibdata1" copy /Y "%OLD_DATA%\ibdata1" "%MYSQL_DATA%\" >nul
if exist "%OLD_DATA%\ib_logfile0" copy /Y "%OLD_DATA%\ib_logfile0" "%MYSQL_DATA%\" >nul
if exist "%OLD_DATA%\ib_logfile1" copy /Y "%OLD_DATA%\ib_logfile1" "%MYSQL_DATA%\" >nul

echo [6/6] Avvio MySQL...
if exist "%XAMPP%\xampp-control.exe" start "" "%XAMPP%\xampp-control.exe"

timeout /t 3 /nobreak >nul

if exist "%MYSQL_BIN%\mysqladmin.exe" (
    "%MYSQL_BIN%\mysqladmin.exe" --user=root ping >nul 2>&1
    if not errorlevel 1 (
        echo.
        echo [OK] MySQL sembra attivo.
        echo Backup completo salvato in:
        echo %SAVE_DIR%
        pause
        exit /b 0
    )
)

echo.
echo [ATTENZIONE] Procedura completata, ma non posso confermare l'avvio automatico.
echo Apri il pannello XAMPP e prova ad avviare MySQL manualmente.
echo.
echo Backup originale salvato in:
echo %SAVE_DIR%
echo.
pause
exit /b 0