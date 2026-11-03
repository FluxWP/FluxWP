<div align="center">

**English** · [Türkçe](README.tr.md)

<img src="docs/logo.svg" alt="Flux Tasks logo" width="120">

# Flux Tasks

**Task and project tracking inside WordPress: lists, boards, calendars, meetings and docs, with an AI assistant.**<br>
Flux Tasks gives the team one place to plan work without another subscription: projects, folders and lists; list, board and calendar views; subtasks, comments with files, notifications and permissions. Meeting lists produce a PDF report, docs are edited block by block, and an assistant on Flux AI can create, update and summarise tasks.

[![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Views](https://img.shields.io/badge/views-list%20%C2%B7%20board%20%C2%B7%20calendar-0a7cff)
![AI](https://img.shields.io/badge/AI-assistant%20%C2%B7%20MCP-7d64ff)
![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)

[Features](#features) · [Screenshots](#screenshots) · [Installation](#installation) · [Roadmap](#roadmap)

<img src="docs/demo.gif" alt="Flux Tasks tour: a list grouped by status, a task with subtasks and comments, the board, the calendar and the usage dashboard" width="900">

</div>

---

## Why Flux Tasks?

Volunteer projects live in WhatsApp groups, spreadsheets and memory. A paid project tool means another login and another bill for every volunteer. Flux Tasks runs where the team already signs in:

- ✅ **Everything in one list.** Who does what by when, with status, priority, tags and custom fields; switch between list, board and calendar.
- 💬 **Talk on the task.** Comments, mentions, files and an activity log sit on the task, not in a chat that scrolls away.
- 📝 **Meetings with minutes.** Agenda, decisions and follow-up subtasks in one place, and a branded PDF report at the end.
- 🤖 **Ask the assistant.** "What is overdue this week?", "Split this into subtasks", "Summarise the comments": through Flux AI, and through MCP for Claude.

## Features

### Work
- Projects, folders and lists with drag-and-drop ordering and moving; per-list statuses.
- Views: **List** (grouped, sortable, inline edit), **Board** (drag between statuses) and **Calendar** (month, week, agenda); saved, filtered views per person or shared.
- Tasks with assignees, dates, priority, tags, custom fields (text, number, date, select, person, link…), nested subtasks, checklist, cover image.
- **My tasks** across all projects; notifications for assignment, mention, status, due and overdue.

### Collaborate
- Comments with mentions and drag-and-drop files (stored outside public access); activity log; watchers.
- Project and list permissions (view, comment, edit, manage); roles chosen in settings.
- Optional e-mails for assignment, mention and due dates, editable in Flux Contact.
- **Meeting lists:** agenda, decisions and subtasks, PDF report with logo and status badges.
- **Docs:** block editor with `/` menu, markdown shortcuts, nested pages, tables, task links and an AI menu (continue, summarise, extract to-dos).

### Manage
- **Usage dashboard:** open, overdue and completed tasks, comments, structure, storage per list and per person.
- AI assistant and quick actions through Flux AI; all tools available to the shared Flux AI chat and over MCP.
- Native screen in the Flux App (`flx-tasks/v1/app/*`); system checks.

## Screenshots

| List view grouped by status | Board |
|---|---|
| <img src="docs/images/list-en.jpg" alt="List view grouped by status" width="440"> | <img src="docs/images/board-en.jpg" alt="Board" width="440"> |
| **Task with subtasks and comments** | **Calendar** |
| <img src="docs/images/task-en.jpg" alt="Task with subtasks and comments" width="440"> | <img src="docs/images/calendar-en.jpg" alt="Calendar" width="440"> |
| **My tasks** | **Meeting list** |
| <img src="docs/images/home-en.jpg" alt="My tasks" width="440"> | <img src="docs/images/meeting-en.jpg" alt="Meeting list" width="440"> |
| **Usage dashboard** | **Dark mode with Flux Admin** |
| <img src="docs/images/dashboard-en.jpg" alt="Usage dashboard" width="440"> | <img src="docs/images/dark-en.jpg" alt="Dark mode with Flux Admin" width="440"> |
| **Mobile list** |   |
| <img src="docs/images/mobile-en.jpg" alt="Mobile list" width="260"> |   |

## Installation

Requirements: WordPress 6.5+, PHP 7.4+. Optional: Flux AI (assistant), Flux Contact (e-mails), Flux Admin (app mode), Flux App.

**From a zip**

1. Download `flx-tasks.zip` from the latest `flx-tasks-v*` release on the [Releases](https://github.com/FluxWP/fluxwp/releases) page.
2. **Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.

**With FluxWP Panel** (recommended on our sites)

**FluxWP Panel › Packages** lists every FluxWP package; click **Install** next to *Flux Tasks*. Updates arrive the same way.

**First steps**

1. Activate Flux Tasks; **Tasks** appears in the admin menu with a starter project and list.
2. In **Tasks › Settings** choose which roles can use tasks, create projects and use AI.
3. Create a project and a list, add tasks, and switch to **Board** or **Calendar**.

> ℹ️ Files are stored in `wp-content/uploads/flx-tasks/`, closed to direct access with `.htaccess`; on Nginx add a `deny all` rule for that folder.

Full setup notes (Turkish): [KURULUM.md](../../docs/gorev-takip/KURULUM.md) · Release notes: [CHANGELOG.md](CHANGELOG.md)

## Roadmap

| | |
|---|---|
| ✅ | Lists, boards and calendars with saved views |
| ✅ | Comments, files, notifications and permissions |
| ✅ | Docs with an AI menu |
| ✅ | Meeting lists with PDF reports |
| ✅ | Native screen in the Flux App |
| ⬜ | Recurring tasks |
| ⬜ | Time tracking |

## License

Flux Tasks Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Made by FluxWP.
