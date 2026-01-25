@echo off
setlocal enabledelayedexpansion

:: Secure Blog CMS - Update Automator
:: This script follows the specific 4-step process to merge and push changes.

echo ====================================================
echo   Secure Blog CMS - Update Automator
echo ====================================================

:: Preliminary: Configure SSH key to avoid passphrase prompts
echo [+] Configuring Git to use new SSH key...
git config core.sshCommand "ssh -i ~/.ssh/id_ed25519_blog_new -o IdentitiesOnly=yes"

:: Preliminary: Ensure current work is committed
echo [+] Staging and committing current changes...
git add .
set /p msg="[?] Enter commit message (leave blank to use default): "
if "!msg!"=="" (
    set msg="Update: Resilience and Anti-Takedown features"
)

:: Fix for pathspec error: Ensure message is properly quoted in the command
git commit -m "!msg!"

echo.
echo [+] Step 1: Updating local repository with latest changes...
git pull origin main

echo.
echo [+] Step 2: Switching to the 'main' branch...
git checkout main

echo.
echo [+] Step 3: Merging 'feature/resilience' into 'main'...
git merge feature/resilience

echo.
echo [+] Step 4: Pushing changes to origin 'main'...
git push -u origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ====================================================
    echo   UPDATE SUCCESSFUL
    echo ====================================================
    echo The resilience features have been merged into main.
) else (
    echo.
    echo [X] Error: Operation failed.
    echo Please check your GitHub SSH key settings or repository rules.
)

echo.
pause
