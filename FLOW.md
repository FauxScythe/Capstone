# SkillBridge AI – System Flow Documentation

> PESO Bago City · Inclusive Job Matching Platform

---

## Table of Contents

1. [Registration Flow](#1-registration-flow)
2. [Login Flow](#2-login-flow)
3. [Job Seeker Flow](#3-job-seeker-flow)
4. [Employer Flow](#4-employer-flow)
5. [PESO Officer Flow](#5-peso-officer-flow)
6. [Database Tables](#6-database-tables)
7. [File Structure](#7-file-structure)

---

## 1. Registration Flow

Applies to both **Job Seekers** and **Employers**.

```
Register Page (register.html)
        │
        ▼
[Step 1] Enter email or mobile number
        │
        ▼
POST /assets/php/email_verifier.php  { action: send_code }
  → Generates 6-digit OTP
  → Sends via PHPMailer (Gmail SMTP)
  → Stores code in $_SESSION['verification']
        │
        ▼
[Step 2] Enter OTP code
        │
        ▼
POST /assets/php/email_verifier.php  { action: verify_code }
  → Validates code + expiry (10 min) + max 3 attempts
  → Sets $_SESSION['verification']['verified'] = true
        │
        ▼
[Step 3] Fill registration form (role: jobseeker | employer)
        │
  ┌─────┴──────┐
  │            │
Job Seeker   Employer
  │            │
  ▼            ▼
Upload Gov ID  Upload Business Permit
  │
  ▼
OCR Validation (ocr_validate.php)
  → Validates ID against name + date of birth
  → Hashes ID image (SHA256) to prevent duplicates
        │
        ▼
POST /assets/php/register.php
  → Checks session verification
  → Validates all fields + password strength
  → Checks duplicate email / name / ID hash
  → Inserts into DB with id_verification = 'verified'
  → Clears verification session
        │
        ▼
Redirect → Login Page
```

**Password Rules:** min 8 characters, 1 uppercase letter, 1 number.

---

## 2. Login Flow

```
Login Page (login.html)
        │
        ▼
Select Role Tab: [ Job Seeker / PWD ] [ Employer ] [ PESO Officer ]
        │
        ▼
Enter email or mobile + password
        │
        ▼
POST /assets/php/login.php
  → Queries correct table based on role
  → Verifies password with password_verify()
  → Sets session:
      $_SESSION['user_id']
      $_SESSION['user_role']   → 'jobseeker' | 'employer' | 'peso_officer'
      $_SESSION['user_name']
      $_SESSION['user_email']
  → Returns JSON { success, redirect }
        │
        ▼
window.location.href → role dashboard
  jobseeker   → assets/templates/jobseeker.html
  employer    → assets/templates/employer.html
  peso_officer→ assets/templates/peso_officer.html
```

**Logout:** All dashboards call `assets/php/logout.php` which runs `session_destroy()` and redirects to `login.html`.

---

## 3. Job Seeker Flow

### 3.1 Dashboard Load

```
jobseeker.html loads
        │
        ▼
DOMContentLoaded → POST /assets/php/jobseeker_dashboard.php
  → Checks session (user_id + role = 'jobseeker')
  → Fetches all profile fields from jobseekers table
  → Returns: name, email, mobile, dob, sex, civil_status,
             address, education, course, skills, experience,
             disability_type, accessibility_needs,
             resume_status, first_time_worker
        │
        ▼
Populates:
  - Welcome message (hero + topbar)
  - Profile form fields (Personal tab)
  - Resume form fields (Resume tab)
  - First-time worker checkbox state
  - window._resumeStatus (used for apply gate)
```

### 3.2 Profile – Personal Tab

Fields: First Name, Middle Name, Last Name, Email, Mobile, Date of Birth, Gender, Civil Status, Complete Address.

```
Click "Save Changes"
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: update_profile }
  → Updates jobseekers table
```

### 3.3 Profile – Resume Tab

Sections: Education, Work Experience, Skills, Disability & Accessibility.

**First-time Worker Checkbox** — when checked, hides the work experience textarea and saves `first_time_worker = 1`.

```
Click "Save Resume"
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: update_resume }
  → Updates: education, course, experience, skills,
             disability_type, accessibility_needs, first_time_worker
```

**Portfolio & Certificates Upload** (inside Work Experience section):

```
Drag & drop or browse files (JPG, PNG, PDF · max 5MB · max 10 files)
        │
        ▼
Click "Upload Files"
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: upload_portfolio }
  → Saves files to uploads/portfolio/
  → Inserts records into jobseeker_portfolio table
        │
Existing files load on tab open:
POST { action: get_portfolio }  → returns file list with paths

Delete file:
POST { action: delete_portfolio, file_id }
  → Deletes file from disk + DB
```

**Print Resume** — opens a print-ready HTML page in a new tab with all resume data pre-filled.

### 3.4 Job Matching

```
Click "Job Matching" nav link
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: get_jobs }
  → Fetches all active job_postings JOIN employers
  → Returns: title, company_name, description, slots,
             employment_type, required_skills, pwd_friendly, posted_date
        │
        ▼
Renders job cards with search + filter (type, PWD friendly)
        │
        ▼
Click "Apply Now"
        │
        ▼
[Gate 1] Check window._resumeStatus
  → If NOT 'approved': show alert, block application
        │
        ▼
[Gate 2] Apply Modal opens
  → Shows job info + live resume summary
  → If education or skills empty: shows warning, disables Submit
        │
        ▼
Click "Submit Application"
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: apply_job }
  → Server re-checks resume_status = 'approved'
  → Checks for duplicate application
  → Inserts into job_applications table
  → Button turns green "✓ Applied"
```

### 3.5 My Applications

```
Click "Applications" nav link
        │
        ▼
POST /assets/php/jobseeker_dashboard.php  { action: get_applications }
  → Fetches job_applications JOIN job_postings JOIN employers
  → Returns: title, company_name, employment_type, applied_date, status
        │
        ▼
Renders application cards with color-coded status badges:
  pending   → amber
  reviewed  → blue
  interview → purple
  hired     → green
  rejected  → red
```

---

## 4. Employer Flow

### 4.1 Dashboard Load

```
employer.html loads
        │
        ▼
DOMContentLoaded → POST /assets/php/employer_dashboard.php
  → Checks session (role = 'employer')
  → Fetches employer profile from employers table
  → Returns: company_name, contact_person, email, phone,
             address, industry, company_size, position,
             accessibility_features, inclusive_hiring
        │
        ▼
Populates:
  - Topbar company name
  - Hero welcome message
  - Company Profile form fields
  → Calls loadJobs() → fetches active vacancies
```

### 4.2 Post a Job Vacancy

```
Click "+ Post New Vacancy"
        │
        ▼
Modal opens with fields:
  Position/Title*, Slots*, Employment Type*,
  Required Skills*, Job Description*,
  PWD Friendly? (Yes/No) → shows Accessibility field if Yes
        │
        ▼
Click "Post Vacancy"
        │
        ▼
POST /assets/php/job_posting.php  { action: post_job }
  → Validates required fields
  → Inserts into job_postings table (status = 'active')
  → Refreshes vacancy table
```

### 4.3 Manage Vacancies

```
Vacancies page shows table:
  Position | Type | Slots | PWD Friendly | Status | Posted | Actions
        │
Search by title/skills + filter by status (active/filled/expired)
        │
Click trash icon → POST { action: delete_job }
  → Verifies job belongs to this employer
  → Deletes from job_postings
```

### 4.4 Company Profile Update

```
Edit Company Information form
        │
        ▼
Click "Save Changes"
        │
        ▼
POST /assets/php/employer_dashboard.php  { action: update_profile }
  → Updates employers table (all profile fields)
  → Updates $_SESSION['user_name']
```

### 4.5 Change Password

```
Enter Current Password + New Password
        │
        ▼
POST /assets/php/employer_dashboard.php  { action: change_password }
  → Verifies current password with password_verify()
  → Validates new password strength
  → Updates hashed password in DB
```

---

## 5. PESO Officer Flow

### 5.1 Dashboard Load

```
peso_officer.html loads
        │
        ▼
DOMContentLoaded → POST /assets/php/peso_officer_dashboard.php
  → Checks session (role = 'peso_officer')
  → Auto-sets all jobseekers id_verification = 'verified'
    (OCR already validated during registration)
  → Fetches officer profile + full stats bundle
        │
        ▼
Renders:
  - Officer name, position, office, email
  - 5 stat cards (seekers, PWD, employers, vacancies, applications)
  - Recent Job Seekers list
  - Employer Activity table
  - Analytics Row 1: Placements, Monthly Trend, AI Match Rate
  - Analytics Row 2: Top Candidates, Skill Gap, Recommended Jobs
  - Analytics Row 3: Active/Inactive Users, Role Distribution,
                     Employer Participation, Recently Registered
  - Analytics Row 4: Interview Stats, Application Status Breakdown
```

### 5.2 Job Seekers Management

```
Click "Job Seekers" nav
        │
        ▼
POST { action: get_seekers }
  → Fetches all jobseekers with resume fields + resume_status
  → Auto-adds resume_status column if missing
        │
        ▼
Table: Name | Email | Mobile | PWD | Resume | Resume Status | Registered

Filters: Search | Resume Status | PWD Type
        │
        ▼
Click "View" (resume button)
        │
        ▼
POST { action: get_seeker_resume, seeker_id }
  → Fetches full profile: personal, education, experience,
    skills, disability, accessibility
        │
        ▼
Resume Modal opens showing all data + current resume status badge
        │
        ▼
Click "Approve Resume" or "Reject Resume"
        │
        ▼
POST { action: update_resume_status, seeker_id, status }
  → Updates resume_status in jobseekers table
  → Updates badge in modal + table row instantly
  → Approved resume → jobseeker can now apply for jobs
```

### 5.3 Employers Management

```
Click "Employers" nav
        │
        ▼
POST { action: get_employers }
  → Fetches all employers
        │
        ▼
Table: Company | Industry | Contact | Email | Phone | Inclusive Hiring | Registered

Search by company name or email
```

### 5.4 Analytics

| Section | Data Source | Description |
|---|---|---|
| Successful Placements | job_applications (status=hired) | Total hired, PWD hired, placement rate, breakdown by industry |
| Monthly Employment Trend | job_applications by month | Bar chart — applications vs hired for last 6 months |
| AI Match Success Rate | job_applications all statuses | Donut chart — hired/interview/reviewed/pending/rejected |
| Top Matched Candidates | job_applications + skill overlap | Per job: ranks applicants by % skill match |
| Skill Gap Analysis | job_postings vs jobseekers skills | Demand vs supply per skill, gap badge |
| Recommended Jobs + AI Confidence | job_postings + application volume | Confidence score ring per active job |
| Active vs Inactive Users | job_applications last 30 days | Active seekers (applied recently) vs inactive |
| Role Distribution | jobseekers + employers + officers | Donut chart by role |
| Employer Participation | employers + job_postings | Bar per employer: jobs posted + applications received |
| Recently Registered | jobseekers + employers UNION | Last 10 registrations across both roles |
| Interview Stats | interviews table (future) | Total, completed, no-show, completion rate |
| Application Status Breakdown | job_applications GROUP BY status | Bar chart + legend with counts and percentages |

---

## 6. Database Tables

| Table | Purpose |
|---|---|
| `jobseekers` | Job seeker accounts and resume data |
| `employers` | Employer accounts and company info |
| `peso_officers` | PESO officer accounts |
| `job_postings` | Job vacancies posted by employers |
| `job_applications` | Applications submitted by job seekers |
| `jobseeker_portfolio` | Portfolio/certificate file uploads |

---

## 7. File Structure

```
Skillbridge/
├── assets/
│   ├── css/
│   │   ├── jobseeker.css
│   │   ├── employer.css
│   │   ├── peso_officer.css
│   │   ├── login.css
│   │   └── register.css
│   ├── php/
│   │   ├── config.php              ← DB connection
│   │   ├── login.php               ← Auth + session setup
│   │   ├── logout.php              ← Session destroy
│   │   ├── register.php            ← Registration handler
│   │   ├── email_verifier.php      ← OTP send/verify
│   │   ├── email_sender_real.php   ← PHPMailer SMTP
│   │   ├── email_config.php        ← SMTP credentials
│   │   ├── ocr_validate.php        ← ID OCR validation
│   │   ├── jobseeker_dashboard.php ← Jobseeker API
│   │   ├── employer_dashboard.php  ← Employer API
│   │   ├── peso_officer_dashboard.php ← Officer API
│   │   ├── job_posting.php         ← Job CRUD API
│   │   └── dashboard.php           ← Legacy redirect
│   └── templates/
│       ├── login.html
│       ├── register.html
│       ├── jobseeker.html          ← Jobseeker SPA
│       ├── employer.html           ← Employer SPA
│       └── peso_officer.html       ← Officer SPA
├── uploads/
│   ├── ids/                        ← Government ID uploads
│   ├── permits/                    ← Business permit uploads
│   └── portfolio/                  ← Jobseeker portfolio files
├── setup_database.php              ← One-time DB setup
├── create_employer.php             ← Dev: seed employer account
├── create_peso_officer.php         ← Dev: seed officer account
└── FLOW.md                         ← This document
```
