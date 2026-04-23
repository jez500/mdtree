# Installation

> This file is part of the [Getting Started](first-steps.md) series.

## Requirements

- PHP 8.3+
- Node.js 20+
- Composer

## Steps

### 1. Clone the repository

```bash
git clone https://github.com/yourorg/mdtree.git
cd mdtree
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` to set your workspace path:

```dotenv
MDTREE_DEFAULT_NAME="My Notes"
MDTREE_DEFAULT_PATH=/path/to/your/notes
```

### 4. Build frontend

```bash
npm run build
```

### 5. Start the server

```bash
php artisan serve
```

Visit [http://localhost:8000](http://localhost:8000).

---

Next: [Configuration](configuration.md) — *Back to: [First Steps](first-steps.md)*
