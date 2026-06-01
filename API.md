# LhgPayrollBundle REST API

Payroll and approval endpoints for Kimai integrations (MCP servers, AI agents, external HR tools).

**Excluded from this API:** Talent, speciality, vendor, and vendor-payment modules (admin UI only).

## Authentication

Use Kimai API token authentication on every request:

| Header | Value |
|--------|--------|
| `X-AUTH-USER` | API user email |
| `X-AUTH-TOKEN` | API token from Kimai profile |

Base URL: `https://your-kimai.example/api/payroll`

All successful responses use:

```json
{
  "code": 200,
  "data": { }
}
```

Errors:

```json
{
  "code": 403,
  "message": "You are not authorized..."
}
```

## Endpoints

### Health

| Method | Path | Description |
|--------|------|-------------|
| GET | `/ping` | Plugin reachability |

### Reference

| Method | Path | Description |
|--------|------|-------------|
| GET | `/statuses` | Approval status codes (1–6) |
| GET | `/period?date=2026-06-01` | Biweekly period `start` / `end` for a reference date |

### Users

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/users` | `view_user` or `api_payroll_view_all` | Users with non-zero `hourly_rate` |
| GET | `/users/accessible` | `api_payroll_view_own` | Users the caller can view payroll for (self / team / all) |

### Biweekly payroll

| Method | Path | Description |
|--------|------|-------------|
| GET | `/biweekly?date=&user_id=` | Timesheets, totals, project breakdown, current approval |

Query parameters:

- `date` (optional): Reference date `Y-m-d` (defaults to today)
- `user_id` (optional): Target user; omit for self. Team lead / super admin only.

### Approval queues

| Method | Path | Description |
|--------|------|-------------|
| GET | `/queues?date=` | Submitted, team-lead-approved, finance-approved, not-submitted lists |

Visible data depends on role and team-lead user preference (same rules as the web UI).

### Approvals

| Method | Path | Body | Description |
|--------|------|------|-------------|
| GET | `/approvals` | — | List approvals (`?status=&start_date=`) |
| GET | `/approvals/{id}` | — | Full approval + timesheets + history |
| POST | `/approvals` | See below | Submit period for approval |
| POST | `/approvals/{id}/status` | See below | Approve / reject / finance approve |
| POST | `/approvals/{id}/resubmit` | — | Re-submit after rejection |

#### Submit (`POST /approvals`)

```json
{
  "userId": 5,
  "startDate": "2026-05-18",
  "endDate": "2026-05-31"
}
```

#### Update status (`POST /approvals/{id}/status`)

Team lead approve (status `2`):

```json
{
  "status": 2,
  "message": "Looks good"
}
```

Finance approve (status `4`):

```json
{
  "status": 4,
  "message": "Paid",
  "commission": 0,
  "adjustment": 50,
  "deduction": 0,
  "netPayable": 1250.5,
  "paymentMethod": "Wise"
}
```

Reject: status `3` (team lead) or `5` (finance).

Payment methods: `Payoneer`, `Paypal`, `Patriot Software`, `Wise`, `Upwork`, `Zelle`.

## Status codes

| Value | Name |
|-------|------|
| 1 | PENDING |
| 2 | APPROVED_BY_TEAM_LEAD |
| 3 | REJECTED_BY_TEAM_LEAD |
| 4 | APPROVED_BY_FINANCE |
| 5 | REJECTED_BY_FINANCE |
| 6 | PAID_BY_FINANCE |

## MCP server mapping (suggested tools)

| Tool name | HTTP |
|-----------|------|
| `payroll_ping` | GET `/api/payroll/ping` |
| `payroll_get_period` | GET `/api/payroll/period` |
| `payroll_get_biweekly` | GET `/api/payroll/biweekly` |
| `payroll_get_queues` | GET `/api/payroll/queues` |
| `payroll_list_approvals` | GET `/api/payroll/approvals` |
| `payroll_get_approval` | GET `/api/payroll/approvals/{id}` |
| `payroll_submit_approval` | POST `/api/payroll/approvals` |
| `payroll_update_approval_status` | POST `/api/payroll/approvals/{id}/status` |
| `payroll_resubmit_approval` | POST `/api/payroll/approvals/{id}/resubmit` |
| `payroll_list_users` | GET `/api/payroll/users` |
| `payroll_list_accessible_users` | GET `/api/payroll/users/accessible` |

## Dependencies

- Kimai 1.11+
- [Kimai ApprovalBundle](https://github.com/kimai/ApprovalBundle) (break-time validation on timesheets)

## Install / routes

After deploying the plugin, clear cache:

```bash
bin/console cache:clear --env=prod
```

API routes load from `Resources/config/routes-api.yaml` (no locale prefix).
