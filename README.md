# DTS Doctrack

A document tracking system for DTI.

## Setup

### Installing PHP

Download and install PHP.

For Windows:

```ps
powershell -c "& ([ScriptBlock]::Create((irm 'https://www.php.net/include/download-instructions/windows.ps1'))) -Version 8.5"
```

For Linux (Debian/Ubuntu):

```bash
# Download and install PHP.
sudo apt update
sudo apt install php -y
```

### Installing Composer

Install [Composer](https://getcomposer.org/download/) in the root project directory via terminal.

In Windows:

```ps
cd C:\path\to\DTS\
```

In Linux:

```bash
cd /path/to/DTS/
```

Then:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

### Installing required dependencies

```bash
php composer.php install
```

### Database setup

Rename `.env.example` to `.env` and fill its values as necessary.

### Running a local server

Run:

```bash
php -S 127.0.0.1:8000
```

Then access the local [login page](http://127.0.0.1:8000/login.php).
