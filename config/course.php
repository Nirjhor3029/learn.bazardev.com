<?php

return [
    // Sibling repo that holds the chapter markdown files (readable on GitHub too).
    'content_path' => env('COURSE_CONTENT_PATH', base_path('../devops-for-developers')),

    'github_repo' => 'https://github.com/Nirjhor3029/devops-for-developers',

    'courses' => [
        'docker' => [
            'title' => 'Docker',
            'tagline' => 'What containers are, why they matter, and Dockerizing a real app — start to finish.',
            'folder' => '01-docker',
            'status' => 'available',
            'chapters' => [
                '01-what-is-docker' => ['title' => 'What is Docker, and why does it matter?', 'status' => 'available'],
                '02-install-and-first-container' => ['title' => 'Install Docker + your first container', 'status' => 'available'],
                '03-images-vs-containers' => ['title' => 'Images vs Containers', 'status' => 'available'],
                '04-dockerfile' => ['title' => 'Dockerfile — write your own image', 'status' => 'available'],
                '05-volumes-and-networking' => ['title' => 'Volumes & Networking', 'status' => 'available'],
                '06-docker-compose' => ['title' => 'Docker Compose — multi-container apps', 'status' => 'available'],
                '07-best-practices-debugging' => ['title' => 'Best practices + debugging', 'status' => 'available'],
                '08-real-project-laravel-dockerize' => ['title' => 'Real project: Dockerize a Laravel app', 'status' => 'available'],
            ],
        ],
        'linux-shell' => [
            'title' => 'Linux & Shell Basics',
            'tagline' => 'The language you use to talk to a server.',
            'folder' => '02-linux-shell',
            'status' => 'available',
            'chapters' => [
                '01-finding-your-way-around' => ['title' => 'Why Linux, and finding your way around', 'status' => 'available'],
                '02-files-and-directories' => ['title' => 'Working with files and directories', 'status' => 'available'],
                '03-permissions-and-ownership' => ['title' => 'Permissions and ownership', 'status' => 'available'],
                '04-users-groups-and-sudo' => ['title' => 'Users, groups, and sudo', 'status' => 'available'],
                '05-processes-and-services' => ['title' => 'Processes and services (systemd)', 'status' => 'available'],
                '06-package-management' => ['title' => 'Package management (apt)', 'status' => 'available'],
                '07-pipes-redirection-text-tools' => ['title' => 'Pipes, redirection, and text tools', 'status' => 'available'],
                '08-ssh-and-remote-access' => ['title' => 'SSH and remote access', 'status' => 'available'],
            ],
        ],
        'git-github' => [
            'title' => 'Git & GitHub (Advanced)',
            'tagline' => 'Version control for working in a team, deep dive.',
            'folder' => '03-git-github',
            'status' => 'available',
            'chapters' => [
                '01-branching-strategies' => ['title' => 'Branching strategies for teams', 'status' => 'available'],
                '02-merge-vs-rebase' => ['title' => 'Merge vs rebase', 'status' => 'available'],
                '03-resolving-merge-conflicts' => ['title' => 'Resolving merge conflicts', 'status' => 'available'],
                '04-interactive-rebase' => ['title' => 'Interactive rebase: cleaning up history', 'status' => 'available'],
                '05-stash-cherry-pick-reflog' => ['title' => 'Stash, cherry-pick, and reflog', 'status' => 'available'],
                '06-pull-requests-and-code-review' => ['title' => 'Pull requests and code review on GitHub', 'status' => 'available'],
                '07-reset-vs-revert' => ['title' => 'Undoing mistakes: reset vs revert', 'status' => 'available'],
                '08-github-power-features' => ['title' => 'GitHub power features', 'status' => 'available'],
            ],
        ],
        'cicd' => [
            'title' => 'CI/CD (GitHub Actions)',
            'tagline' => 'Push code, get automatic tests and deploys.',
            'folder' => '04-cicd',
            'status' => 'available',
            'chapters' => [
                '01-what-is-cicd' => ['title' => 'What is CI/CD, and why does it matter?', 'status' => 'available'],
                '02-github-actions-basics' => ['title' => 'GitHub Actions basics: workflows, jobs, steps', 'status' => 'coming_soon'],
                '03-triggers-and-events' => ['title' => 'Triggers: events, branches, paths, schedules', 'status' => 'coming_soon'],
                '04-env-vars-secrets-matrix' => ['title' => 'Environment variables, secrets, and matrix builds', 'status' => 'coming_soon'],
                '05-caching-and-artifacts' => ['title' => 'Caching and artifacts', 'status' => 'coming_soon'],
                '06-automated-testing-in-ci' => ['title' => 'Automated testing in CI', 'status' => 'coming_soon'],
                '07-continuous-deployment' => ['title' => 'Continuous deployment: shipping automatically', 'status' => 'coming_soon'],
                '08-real-project-laravel-pipeline' => ['title' => 'Real project: a full pipeline for a Dockerized Laravel app', 'status' => 'coming_soon'],
            ],
        ],
        'kubernetes' => ['title' => 'Kubernetes', 'tagline' => 'Managing many containers at scale.', 'folder' => '05-kubernetes', 'status' => 'coming_soon', 'chapters' => []],
        'cloud' => ['title' => 'Cloud (AWS / VPS Basics)', 'tagline' => 'Provisioning servers, domains, and deployment.', 'folder' => '06-cloud', 'status' => 'coming_soon', 'chapters' => []],
        'monitoring' => ['title' => 'Monitoring & Logging', 'tagline' => 'Knowing what is happening in production.', 'folder' => '07-monitoring', 'status' => 'coming_soon', 'chapters' => []],
        'iac' => ['title' => 'Infra as Code (Terraform/Ansible)', 'tagline' => 'Writing server setup as code.', 'folder' => '08-iac', 'status' => 'coming_soon', 'chapters' => []],
    ],
];
