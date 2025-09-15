# Proyecto Final - Draftosaurus Digital

Este proyecto es una adaptación digital del juego de mesa Draftosaurus. Lo desarrollamos como parte del proyecto final de nuestra carrera, integrando conocimientos de desarrollo web, estructuras de datos y diseño de interfaces.

## Objetivo

Trasladar la experiencia del juego físico a una plataforma online, aplicando conceptos de programación y diseño vistos durante el año.

## Tecnologías utilizadas

- Frontend: HTML5, CSS, JavaScript
- Backend: PHP, MySQL

---

## Estructura del proyecto

1. Autenticación de Usuarios
   - Login: desarrollamos la lógica de acceso para usuarios registrados.
   - Registro: permitimos la creación de nuevas cuentas.
2. Menús principales
   - Menú de Juego: diseñamos una pantalla inicial donde se puede acceder a las partidas, consultar las reglas o cerrar sesión.
   - Menú de Administrador: implementamos una sección exclusiva para administrar usuarios, partidas y estadísticas.
3. Juego
   - Interfaz de Juego: creamos el tablero digital, la gestión de turnos y las restricciones basadas en recintos y en el dado.
     - Pre‑interfaz: selección de rival para crear una nueva partida.
4. Volver a Jugar
   - Interfaz para continuar partidas en curso.
     - Pre-Interfaz: selección de partidas pendientes (IN_PROGRESS) para reanudar
5. Seguimiento
   - Interfaz de Seguimiento: incorporamos el cálculo automático de puntos y la validación de reglas.
6. Cómo Jugar
   - Instrucciones: redactamos una guía clara con las reglas del juego.

## Árbol de carpetas

```
/ProyectoFinalTercero
│
├── backend/
│   └── api/
│       ├── controllers/
│       ├── repositories/
│       ├── services/
│       ├── routers/
│       ├── utils/
│       └── config/
│
├── frontend/
│   ├── fonts/
│   ├── img/
│   ├── styles/
│   ├── js/
│   ├── login.html
│   ├── register.html
│   ├── menu.html
│   ├── juego.html
│   ├── seguimiento.html
│   ├── comojugar.html
│   ├── volverAJugar.html
│   └── preVolverAJugar.html
│
├── test-data/
│
└── README.md
```

---

## Instalación y uso

1. Clonar el repositorio:

```bash
git clone https://github.com/jcaguss/ProyectoFinalTercero.git
```

2. Configuración de base de datos (MySQL)

- Ingresa al archivo config/Database.php y configura las variables segun su usuario

```
host:     localhost ( Por defecto )
dbname:   draftosaurus ( Nombre de la base de datos )
usuario:  itiuser (Por defecto es host)
password: iti2025UserPrueba (Contraseña de ejemplo)
```

3. Iniciar el servidor backend (desde la carpeta del proyecto):

- Abrir una terminal en ProyectoFinalTercero.
- Ejecutar:

```powershell
php -S localhost:8000 -t backend/api
```

- Mantener esta terminal abierta mientras se usa la app.

4. Servir el frontend (recomendado con Live Server):

- Abrir la carpeta frontend con Live Server (http://127.0.0.1:5500 o http://localhost:5500).
- Si usás otro origen/puerto, ajustar CORS en backend/api/index.php.

---

5. Prueba de recurrencia de partidas.

- La base de datos se encueuntra en la carpeta de /test-data

  - Ejecuta el script de draftosaurus_database.sql en tu conexion de MYSQl
  - Para poder probar una partida debes de registrar el usuario que se encuentra en register-test.json
  - Luego ejecuta el script partida_sin_terminar_agustin.sql

## Equipo de desarrollo

- Arias Emiliano
- Alcoba Tamara
- Cáceres Agustín
- Asconeguy Bruno
- Ormaechea Mayra
