# 🚀 Docker Composer Proxy Dashboard

[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

**Docker Composer Proxy Dashboard** es una aplicación web diseñada para **gestionar y desplegar proyectos Docker Compose con proxy inverso** de manera sencilla y automatizada. Permite configurar, iniciar y administrar servicios de infraestructura y aplicaciones en contenedores.

📖 **Available Languages:**
- 🇬🇧 [English](README.md)
- 🇪🇸 [Español](README_ES.md) (Current)

## 🌟 Características Principales

✅ **Gestión de Infraestructura**: Detecta servicios requeridos por las aplicaciones y los agrega dinámicamente a un `docker-compose.infrastructure.yml`.  
✅ **Despliegue Automático**: Levanta la infraestructura antes de iniciar cualquier aplicación.  
✅ **Proxy Dinámico**: Asigna dominios y rutas a los contenedores para permitir el acceso a los servicios desplegados.  
✅ **Control de Acceso**: Implementa autenticación con Google OAuth para restringir el acceso a usuarios autorizados.  
✅ **Gestión de Credenciales**: Genera y asigna automáticamente credenciales de acceso a bases de datos y otros servicios compartidos.  

## 🚀 Uso

✅ **Definir servicios de infraestructura**: Bases de datos comunes, servidores de correo, colas o caches...  
✅ **Definir credenciales para infraestructuras**: Gestionando de forma automática la creación y configuración de las mismas.  
✅ **Definir Aplicaciones y los Servicios que necesitan**: configurando las variables de entorno en donde asociar las credenciales a los servicios  
✅ **Desplegar Aplicaciones**: Se inicia cada aplicación con su docker-compose.yml, conectándola a la infraestructura común.  

