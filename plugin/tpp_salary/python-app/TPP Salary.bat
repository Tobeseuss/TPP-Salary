@echo off
chcp 65001 >nul
title TPP Salary - نسخه آفلاین
cd /d "%~dp0"

rem ---- پیدا کردن پایتون (py یا python) ----
set PYEXE=
where py >nul 2>nul && set PYEXE=py -3
if "%PYEXE%"=="" where python >nul 2>nul && set PYEXE=python

if "%PYEXE%"=="" (
    echo.
    echo  ============================================================
    echo   Python یافت نشد!
    echo   لطفا Python 3.10 یا جدیدتر را از python.org نصب کنید
    echo   در زمان نصب گزینه "Add python.exe to PATH" را تیک بزنید
    echo  ============================================================
    echo.
    pause
    exit /b 1
)

rem ---- اجرای برنامه (نصب خودکار وابستگی‌ها در بار اول) ----
%PYEXE% "tpp_salary_app.py"
if errorlevel 1 (
    echo.
    echo اجرای برنامه با خطا مواجه شد؛ پیام بالا را بررسی کنید.
    pause
)
