# 🚀 Docker Composer Proxy Dashboard

[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

**Docker Composer Proxy Dashboard** is a web application designed to **manage and deploy Docker Compose projects with a reverse proxy** in a simple and automated way. It allows you to configure, start, and manage infrastructure services and applications in containers.

📖 **Available Languages:**
- 🇬🇧 [English](README.md) (Current)
- 🇪🇸 [Español](README_ES.md)

## 🌟 Key Features

✅ **Infrastructure Management**: Detects required services for applications and dynamically adds them to `docker-compose.infrastructure.yml`.  
✅ **Automatic Deployment**: Ensures infrastructure is up before launching any application.  
✅ **Dynamic Proxy**: Assigns domains and routes to containers for seamless access to deployed services.  
✅ **Access Control**: Implements Google OAuth authentication to restrict access to authorized users.  
✅ **Credential Management**: Automatically generates and assigns credentials for databases and other shared services.  

## 🚀 Usage

✅ **Define Infrastructure Services**: Common databases, mail servers, queues, or caches...  
✅ **Manage Infrastructure Credentials**: Automatically handling their creation and configuration.  
✅ **Define Applications and Their Required Services**: Configuring environment variables to associate credentials with services.  
✅ **Deploy Applications**: Each application is launched with its docker-compose.yml, connecting it to the shared infrastructure.  

