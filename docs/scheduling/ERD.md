# ERD — Security Patrol Management System (Scheduling)

Diagram di bawah mencakup tabel existing yang relevan dan tabel baru Task 01.
Tidak ada perubahan schema pada Task 06–08; kolom dan relasi di bawah adalah
kondisi final modul penjadwalan.

```mermaid
erDiagram
    UNITS {
        bigint id PK
        bigint parent_id FK
        string unit_code UK
        string unit_name UK
        enum unit_level
        boolean is_active
        string guest_book_qr_code UK
    }

    USERS {
        bigint id PK
        bigint unit_id FK
        string username UK
        string name
        string email UK
        enum role
        boolean is_active
    }

    SECURITY_POSTS {
        bigint id PK
        string post_code UK
        string name
        text address
        decimal latitude
        decimal longitude
        bigint unit_id FK
    }

    PATROL_POINTS {
        bigint id PK
        uuid qr_code UK
        string name
        bigint security_post_id FK
    }

    SECURITIES {
        bigint id PK
        bigint user_id FK,UK
        string registration_number UK
        string full_name
        string kta_number UK
        date kta_issued_date
        date kta_expired_date
    }

    SECURITY_TEAMS {
        bigint id PK
        bigint security_post_id FK
        string name
        string code
        text description
        boolean is_active
    }

    SECURITY_TEAM_MEMBERS {
        bigint id PK
        bigint security_team_id FK
        bigint security_id FK
        date joined_at
        date left_at
    }

    SHIFTS {
        bigint id PK
        bigint unit_id FK
        string name
        string code
        time start_time
        time end_time
        tinyint end_day_offset
        string color
        boolean is_active
    }

    SCHEDULE_PATTERNS {
        bigint id PK
        bigint unit_id FK
        string name
        text description
        smallint cycle_length
        boolean is_active
    }

    SCHEDULE_PATTERN_DETAILS {
        bigint id PK
        bigint schedule_pattern_id FK
        smallint day_order
        bigint shift_id FK
    }

    SCHEDULE_PERIODS {
        bigint id PK
        bigint unit_id FK
        bigint security_post_id FK
        bigint security_team_id FK
        string name
        date start_date
        date end_date
        enum status
        bigint created_by FK
    }

    SCHEDULE_PERIOD_MEMBERS {
        bigint id PK
        bigint schedule_period_id FK
        bigint security_id FK
        bigint schedule_pattern_id FK
        smallint start_offset
    }

    SCHEDULES {
        bigint id PK
        bigint schedule_period_id FK
        bigint schedule_period_member_id FK
        bigint security_id FK
        bigint shift_id FK
        bigint schedule_pattern_id FK
        smallint pattern_day_order
        date date
        enum source
        text notes
        bigint created_by FK
        bigint updated_by FK
    }

    UNITS ||--o{ UNITS : "parent"
    UNITS ||--o{ USERS : "has users"
    UNITS ||--o{ SECURITY_POSTS : "has posts"
    UNITS ||--o{ SHIFTS : "has shifts"
    UNITS ||--o{ SCHEDULE_PATTERNS : "has patterns"
    UNITS ||--o{ SCHEDULE_PERIODS : "has periods"

    USERS ||--|| SECURITIES : "authenticates"
    SECURITY_POSTS ||--o{ PATROL_POINTS : "has patrol points"
    SECURITY_POSTS ||--o{ SECURITY_TEAMS : "has teams"
    SECURITY_POSTS ||--o{ SCHEDULE_PERIODS : "has periods"

    SECURITY_TEAMS ||--o{ SECURITY_TEAM_MEMBERS : "has memberships"
    SECURITIES ||--o{ SECURITY_TEAM_MEMBERS : "joins teams"

    SCHEDULE_PATTERNS ||--o{ SCHEDULE_PATTERN_DETAILS : "has rotation days"
    SHIFTS ||--o{ SCHEDULE_PATTERN_DETAILS : "used by patterns"

    SECURITY_TEAMS ||--o{ SCHEDULE_PERIODS : "scheduled in periods"
    USERS ||--o{ SCHEDULE_PERIODS : "created_by"

    SCHEDULE_PERIODS ||--o{ SCHEDULE_PERIOD_MEMBERS : "has snapshot"
    SECURITIES ||--o{ SCHEDULE_PERIOD_MEMBERS : "snapshot member"
    SCHEDULE_PATTERNS ||--o{ SCHEDULE_PERIOD_MEMBERS : "assigned pattern"

    SCHEDULE_PERIODS ||--o{ SCHEDULES : "has actual schedules"
    SCHEDULE_PERIOD_MEMBERS ||--o{ SCHEDULES : "owns schedule rows"
    SECURITIES ||--o{ SCHEDULES : "scheduled person"
    SHIFTS ||--o{ SCHEDULES : "used shift"
    SCHEDULE_PATTERNS ||--o{ SCHEDULES : "source pattern"
    USERS ||--o{ SCHEDULES : "created_by/updated_by"
```

## Catatan Relasi

- `securities` **tidak** memiliki `unit_id`/`security_post_id`.
- Unit satpam ditelusuri melalui `securities.user_id -> users.unit_id`.
- Pos satpam ditentukan melalui `security_team_members -> security_teams.security_post_id`.
- `schedule_period_members` adalah **snapshot**; tidak memiliki FK ke `security_team_members`.
- `schedules` menyimpan hasil aktual dan tidak dihitung ulang saat master pola berubah.

## Constraint Penting

- `security_teams`: unique `(security_post_id, code)` dan `(security_post_id, name)`.
- `security_team_members`: unique `(security_team_id, security_id, joined_at)`; index `(security_id, left_at)` dan `(security_team_id, left_at)`.
- `shifts`: unique `(unit_id, code)` dan `(unit_id, name)`; `start_time`/`end_time` nullable; `end_day_offset` default 0.
- `schedule_patterns`: unique `(unit_id, name)`; `cycle_length` smallint unsigned.
- `schedule_pattern_details`: unique `(schedule_pattern_id, day_order)`; FK shift restrict; FK pola cascade.
- `schedule_periods`: status enum `draft|active|completed`; index `(security_team_id, start_date, end_date)`, `(unit_id, status)`, `(security_post_id, status)`.
- `schedule_period_members`: unique `(schedule_period_id, security_id)`; `schedule_pattern_id` nullable; `start_offset` default 0.
- `schedules`: unique `(security_id, date)`; `source` enum `system_generated|adjustment`; `shift_id`, `schedule_pattern_id`, `pattern_day_order`, `notes`, dan `updated_by` nullable.

## Semantik Tanggal

- Rentang periode dan keanggotaan regu bersifat inklusif.
- `left_at = null` berarti anggota masih aktif.
- Query rentang tanggal memakai `whereDate` agar konsisten antara SQLite dan
  MySQL (SQLite dapat menyimpan `date` dengan komponen waktu `00:00:00`).

## Status Implementasi

| Task | Status |
|---|---|
| 01 Fondasi schema/model | Completed |
| 02 Master Shift | Completed |
| 03 Master Pola Rotasi | Completed |
| 04 Periode + snapshot | Completed |
| 05 Generator | Completed |
| 06 Penyesuaian manual | Completed |
| 07 Kalender & monitoring | Completed |
| 08 Final QA + dokumentasi | Completed |
| CRUD Regu/Anggota Regu | Completed (Task 09) |
