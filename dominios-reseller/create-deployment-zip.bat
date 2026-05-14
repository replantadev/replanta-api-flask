@echo off
echo ========================================
echo   CREAR ZIP DEPLOYMENT v1.5.7
echo ========================================
echo.

echo Creando directorio temporal para deployment...

:: Crear directorio temporal
mkdir temp_deploy 2>nul

:: Copiar archivos modificados
echo Copiando archivos modificados...
copy "dominios-reseller.php" "temp_deploy\"
copy "CHANGELOG.md" "temp_deploy\"
copy "includes\class-onboarding-db.php" "temp_deploy\"
copy "includes\class-onboarding-worker.php" "temp_deploy\"
copy "includes\class-debug-hub.php" "temp_deploy\"
copy "includes\class-openprovider-service.php" "temp_deploy\"
copy "SOLUCION-NS-ES.md" "temp_deploy\"

:: Crear ZIP usando PowerShell
echo Creando archivo ZIP...
powershell "Compress-Archive -Path 'temp_deploy\*' -DestinationPath 'dominios-reseller-1.5.7-deployment.zip' -Force"

:: Limpiar temporal
rmdir /s /q temp_deploy

echo.
echo ========================================
echo   DEPLOYMENT ZIP CREADO
echo ========================================
echo.
echo Archivo: dominios-reseller-1.5.7-deployment.zip
echo.
echo INSTRUCCIONES DE DEPLOYMENT:
echo 1. Subir el ZIP al servidor
echo 2. Extraer en: /wp-content/plugins/dominios-reseller/
echo 3. Verificar version 1.5.7 en el plugin
echo 4. Probar Debug Hub - nuevo diagnostico Openprovider
echo 5. Probar onboarding de dominio .es
echo.
echo NOTA: Los dominios .es requeriran configuracion manual de NS
echo.
pause