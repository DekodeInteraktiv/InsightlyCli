@echo off

REM Start by building the PHP script
php build.php

REM Set up the Dekode directory if it does not already exist
if not exist "%appdata%\Dekode\InsightlyCLI\NUL" mkdir "%appdata%\Dekode\InsightlyCLI"

REM Copy the insightly cli file to the newly created directory
SET COPYCMD=\Y && move insightly-cli.phar "%appdata%\Dekode\InsightlyCLI\insightly-cli.phar"

REM Delete the batch file if it exists, in case updates were made
if exist "%appdata%\Dekode\InsightlyCLI\isc.bat" del "%appdata%\Dekode\InsightlyCLI\isc.bat"

REM Write the isc.bat file to actually run the commands through
@echo @echo OFF > "%appdata%\Dekode\InsightlyCLI\isc.bat"
@echo :: in case DelayedExpansion is on and a path contains ! > "%appdata%\Dekode\InsightlyCLI\isc.bat"
@echo setlocal DISABLEDELAYEDEXPANSION > "%appdata%\Dekode\InsightlyCLI\isc.bat"
@echo php "%appdata%\Dekode\InsightlyCLI\insightly-cli.phar" %%* > "%appdata%\Dekode\InsightlyCLI\isc.bat"


REM Check if system variables have previously been setm if not add our new path
echo %path% | find "Dekode\InsightlyCLI" > nul
if errorlevel 1 (echo Add "%appdata%\Dekode\InsightlyCLI\" to your system path)

REM Delete the compressed version of the file not being used
del insightly-cli.phar.gz /f /q