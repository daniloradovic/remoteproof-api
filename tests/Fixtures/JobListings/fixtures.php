<?php

declare(strict_types=1);

/*
 * Real-world style job descriptions used for prompt benchmarking (TASK 7).
 *
 * Each fixture mirrors a row from the Task 7 table in remoteproof-laravel-api-plan.md.
 * Descriptions are paraphrased composites of public listings — long enough to
 * exercise the classifier and to satisfy the 100-character validation rule on
 * the /api/classify endpoint.
 */

return [
    [
        'id' => 1,
        'source' => 'LinkedIn',
        'expected' => 'WORLDWIDE',
        'note' => 'Explicitly says "work from anywhere"',
        'text' => <<<'TEXT'
Senior Backend Engineer (Fully Remote — Work From Anywhere)

About Toggl Track
Toggl Track is a time tracking tool used by thousands of teams across more than 120 countries. We are a fully distributed company and have been remote-first since day one.

The role
We are looking for a Senior Backend Engineer to join our Platform team. You will design, build and operate the services that power our REST API, our integrations and our reporting pipeline.

Where you can work from
This role is fully remote and open to candidates anywhere in the world. There are no timezone restrictions and no requirement to relocate. You choose your hours, you choose your country, you choose your setup.

What you will do
- Own backend services written in Go and Ruby
- Improve the performance and reliability of our public API
- Pair with product designers and engineers across four continents
- Take part in a weekly on-call rotation that follows the sun

Requirements
- 5+ years of backend engineering experience
- Strong API design fundamentals
- Comfortable working asynchronously with written communication

Benefits include four weeks of paid vacation, an annual team retreat, and a generous home office budget.
TEXT,
    ],

    [
        'id' => 2,
        'source' => 'LinkedIn',
        'expected' => 'RESTRICTED',
        'note' => 'US work auth required',
        'text' => <<<'TEXT'
Staff Software Engineer, Payments — Remote (United States)

Stripe is hiring a Staff Engineer to join the Payments Reliability group. This role is remote within the United States.

What you will do
- Lead the design of high-throughput payment routing systems
- Mentor a team of senior engineers
- Partner with product, risk and compliance stakeholders

Requirements
- 8+ years of backend engineering experience at scale
- Deep expertise in distributed systems and databases
- Must be authorized to work in the United States without visa sponsorship now or in the future
- Must currently reside in the United States; we are unable to support relocation from outside the US for this role

Compensation
The base salary range for this role is $215,000 to $285,000 USD plus equity and benefits. Final compensation depends on the work location within the US.
TEXT,
    ],

    [
        'id' => 3,
        'source' => 'Indeed',
        'expected' => 'RESTRICTED',
        'note' => 'EU timezone only',
        'text' => <<<'TEXT'
Senior Site Reliability Engineer — Remote (Europe)

Contentful is looking for a Senior SRE to join our Berlin-based Reliability team. The role is remote, but we hire only candidates whose normal working hours fall within Central European Time (UTC+1) plus or minus three hours.

Why this matters
Our incident response process and team rituals all happen in CET working hours. We have found that engineers outside this overlap window are not able to be effective in this role, so we are not considering applicants in the Americas, Asia or Oceania at this time.

You will
- Operate our Kubernetes platform across multiple AWS regions
- Improve our SLOs and on-call experience
- Partner with product engineering on reliability reviews

You bring
- 5+ years SRE / infrastructure experience
- Strong Linux, networking and Terraform skills
- Eligibility to work in an EU/EEA country or the UK

Benefits include a German-style pension contribution, 30 days of paid vacation and a Berlin co-working stipend.
TEXT,
    ],

    [
        'id' => 4,
        'source' => 'We Work Remotely',
        'expected' => 'WORLDWIDE',
        'note' => 'Deel mentioned as payroll',
        'text' => <<<'TEXT'
Full-Stack Engineer — 100% Remote, Global

Plausible Analytics is a small, fully remote, fully bootstrapped company. We are hiring a full-stack engineer to help us scale our privacy-friendly analytics product.

Location
You can be based anywhere in the world. We are a globally distributed team and we work asynchronously across many timezones. There is no preferred timezone and no required overlap window.

How we hire globally
We use Deel to handle contracts, payroll and local compliance, which lets us hire engineers as full-time contractors regardless of which country you live in. Whether you are in Argentina, Kenya, Vietnam or Portugal, we can make it work.

What you will do
- Ship features across our Elixir/Phoenix backend and our React frontend
- Improve performance of our analytics ingestion pipeline
- Talk to customers directly and turn their feedback into product changes

What we offer
- 6 weeks of paid vacation per year
- Profit sharing
- A real four-day work week
TEXT,
    ],

    [
        'id' => 5,
        'source' => 'LinkedIn',
        'expected' => 'UNCLEAR',
        'note' => 'Says remote, zero context',
        'text' => <<<'TEXT'
Software Engineer — Remote

Acme Corp is hiring a Software Engineer to join our growing engineering team.

About the role
This is a remote position. You will work on customer-facing features in our web application and contribute to our backend services.

Responsibilities
- Build and maintain features in our product
- Collaborate with designers, PMs and other engineers
- Participate in code reviews and architecture discussions
- Help improve our test coverage and CI pipeline

Requirements
- 3+ years of professional software engineering experience
- Comfortable with at least one modern backend language and one frontend framework
- Strong written communication skills
- A passion for building great products

About Acme Corp
We build software that helps small businesses run more efficiently. We are a friendly team that values curiosity, ownership and craftsmanship.
TEXT,
    ],

    [
        'id' => 6,
        'source' => 'Indeed',
        'expected' => 'RESTRICTED',
        'note' => 'Restriction buried in paragraph 4',
        'text' => <<<'TEXT'
Senior Data Engineer — Remote

Pellucid Data is a Series B analytics company helping operators in the energy sector understand their assets. We are hiring a Senior Data Engineer to build the next generation of our data platform.

What you will work on
You will own the ingestion pipelines that pull telemetry from thousands of devices into our warehouse. You will design schemas, optimise dbt models and partner with our analytics team to ship dashboards that customers actually use.

Tech stack
We run on Python, dbt, Snowflake, Airflow and a small amount of Go for performance-critical ingestion. We deploy on AWS with Terraform and use GitHub Actions for CI/CD.

Eligibility
This is a fully remote position, however due to data residency obligations under our customer contracts we are only able to hire candidates who are legally authorised to work in Canada and who reside in Canada at the time of hire. We are not able to sponsor work permits or hire contractors outside of Canada for this role.

Benefits include four weeks of paid vacation, RRSP matching and a generous home office budget.
TEXT,
    ],

    [
        'id' => 7,
        'source' => 'LinkedIn',
        'expected' => 'RESTRICTED',
        'note' => '"Must reside in US or Canada"',
        'text' => <<<'TEXT'
Engineering Manager, Platform — Remote (North America)

Linear is hiring an Engineering Manager to lead our Platform team. The role is fully remote.

Where you can work from
You must reside in the United States or Canada for this role. We are not able to consider candidates based in Mexico, Latin America, Europe, Asia or anywhere else outside the US and Canada at this time.

What you will do
- Manage and grow a team of 6-8 senior engineers
- Set the technical direction for our core platform
- Partner with product and design leadership on roadmap planning
- Run performance reviews, hiring loops and 1:1s

What we are looking for
- 3+ years of engineering management experience
- A track record of shipping high quality software at scale
- Strong written communication skills (we work async-first)
- Eligibility to work in either the US or Canada without visa sponsorship

Benefits include equity, full health coverage, a generous PTO policy and an annual team offsite.
TEXT,
    ],

    [
        'id' => 8,
        'source' => 'We Work Remotely',
        'expected' => 'WORLDWIDE',
        'note' => 'Multiple continents listed',
        'text' => <<<'TEXT'
Product Engineer — Remote, Worldwide

GitLab is an all-remote company with team members in over 65 countries. We are hiring a Product Engineer to join one of our Stage groups.

Where you can work from
This role is open globally. We currently employ team members across North America, South America, Europe, Africa, Asia and Oceania, and we welcome applicants from any of these regions. There is no required base location and no required timezone overlap beyond a few hours of async-friendly handoff per week.

What you will do
- Ship features end-to-end across the GitLab product
- Pair with engineers, designers and PMs from around the world
- Contribute to our public handbook and engineering blog
- Take part in our follow-the-sun on-call rotation

Why GitLab
- Truly all-remote — every meeting is recorded, every decision is written down
- Generous PTO and a paid sabbatical after five years
- Equity in a public company
- Annual in-person Contribute event in a different country every year
TEXT,
    ],

    [
        'id' => 9,
        'source' => 'LinkedIn',
        'expected' => 'UNCLEAR',
        'note' => '"Overlap with EST preferred"',
        'text' => <<<'TEXT'
Backend Engineer — Remote

Northwind Software is hiring a Backend Engineer to join our small but growing engineering team.

About the role
This is a remote role. You will work on our Python/Django backend, design and ship REST APIs, and partner closely with our frontend engineers and product team.

Working hours
We are a globally distributed team but most of our product, design and customer success folks are based on the US East Coast. A few hours of overlap with Eastern Time (roughly 9am-12pm EST) would be preferred so that you can join standups and pairing sessions, but it is not a hard requirement.

Responsibilities
- Build and maintain backend services in Python/Django
- Design REST APIs consumed by our web and mobile clients
- Improve test coverage and CI/CD pipelines
- Take part in code reviews

Requirements
- 4+ years of backend engineering experience
- Strong Python skills
- Good written English
TEXT,
    ],

    [
        'id' => 10,
        'source' => 'Indeed',
        'expected' => 'RESTRICTED',
        'note' => 'UK only, benefits mention NHS',
        'text' => <<<'TEXT'
Senior Frontend Engineer — Remote (UK)

Monzo is hiring a Senior Frontend Engineer to join our Web Platform team.

Location
This role is remote, but you must be based in the United Kingdom and have the right to work in the UK without visa sponsorship. You can work from anywhere in the UK; we have hubs in London and Cardiff for those who want occasional in-person time.

What you will do
- Build features in our React/TypeScript codebase
- Improve performance of our customer-facing web app
- Partner with designers and product managers to ship great UX

Benefits
- Private medical insurance that complements your NHS coverage
- 32 days of paid holiday (including UK bank holidays)
- Pension contributions via our UK workplace pension scheme
- Cycle-to-work scheme and season ticket loan
- £1,000 yearly learning budget

Why Monzo
We are building a bank that people love to use. Join us and help us reach 10 million UK customers.
TEXT,
    ],
];
