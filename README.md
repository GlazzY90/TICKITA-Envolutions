# TICKITA

A Support Ticket Portal built with Laravel, React, and MySQL.

Clients can create and manage tickets for their own organization, while support agents can manage tickets across all organizations.

## Tech Stack

### Backend
- Laravel 13
- PHP 8.3
- Laravel Sanctum
- Eloquent ORM
- MySQL 8

### Frontend
- React
- React Router
- Axios
- Vite

### Testing & CI
- PHPUnit / Laravel Feature Tests
- Laravel Pint
- GitHub Actions
- MySQL 8.4 in CI

Laravel handles validation, authorization, business logic, persistence, and API serialization, while React handles presentation and user interaction.

## Core Domain

The system uses four main entities:

- **Organization** — represents a client company.
- **User** — either a `client` or `support_agent`.
- **Ticket** — belongs to exactly one organization and stores status, priority, assignment, and SLA information.
- **Ticket Message** — represents either a public reply or an internal support note.

### Client Users

Clients can:
- Create tickets for their own organization
- View their organization's tickets
- View ticket details
- Add public replies

Clients cannot:
- Access another organization's tickets
- Change status, priority, or assignment
- Create or view internal notes

### Support Agents

Support agents can:
- View tickets across all organizations
- Search and filter tickets
- Change status and current priority
- Assign or unassign support agents
- Add public replies
- Add internal notes

Ticket authorization is enforced through `TicketPolicy`.

Internal notes are filtered at the database-query level, so they are never included in client API responses.

## Ticket Lifecycle

Supported statuses:
- `open`
- `in_progress`
- `resolved`
- `closed`

Supported priorities:
- `low`
- `normal`
- `high`

Tickets store both:

```text
initial_priority
priority
```

`initial_priority` is used to calculate the original SLA deadline, while `priority` represents the current priority and may later be changed by support agents.

Changing the current priority does **not** recalculate the original SLA deadline.

## SLA Rules

| Priority | Resolution SLA |
| -------- | --------------: |
| High     |  4 hours |
| Normal   | 24 hours |
| Low      | 72 hours |

The UI displays:
- On Track
- Due Soon
- Overdue
- Completed

`Due Soon` means 30 minutes or less remain before the SLA deadline.

Resolved or closed tickets are marked as `Completed`.

## Search & Filtering

Support agents can:
- Search ticket titles and descriptions
- Filter by organization
- Filter by status
- Filter by priority

## Installation

### Requirements
- PHP 8.3+
- Composer
- Node.js and npm
- MySQL 8 or compatible MariaDB
- Git

### 1. Clone the repository

```bash
git clone <repository-url>
cd <repository-directory>
```

### 2. Create the database

```sql
CREATE DATABASE tickita
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Copy the environment file:

```bash
cp .env.example .env
```

Configure your MySQL credentials in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tickita
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Install dependencies and set up the app

```bash
composer install
npm install

php artisan key:generate
php artisan migrate:fresh --seed
```

### 4. Start development

Start Laravel:

```bash
php artisan serve
```

Start Vite in another terminal:

```bash
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

## Development Accounts

| Role          | Email               | Password |
| ------------- | -------------------- | -------- |
| Acme Client   | client@acme.test     | password |
| Globex Client | client@globex.test   | password |
| Support Agent | agent@support.test   | password |

These accounts exist only for local development.

## Testing

Create a separate test database:

```sql
CREATE DATABASE tickita_test
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Run tests:

```bash
php artisan test
```

Check PHP formatting:

```bash
vendor/bin/pint --test
```

Build the frontend:

```bash
npm run build
```

## Continuous Integration

GitHub Actions runs on development branches, pull requests to `main`, and pushes to `main`, verifying:

```text
Laravel Pint → MySQL migrations + seeders → Laravel tests → React production build
```