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
                '07-best-practices-debugging' => ['title' => 'Best practices + debugging', 'status' => 'coming_soon'],
                '08-real-project-laravel-dockerize' => ['title' => 'Real project: Dockerize a Laravel app', 'status' => 'coming_soon'],
            ],
        ],
        'linux-shell' => ['title' => 'Linux & Shell Basics', 'tagline' => 'The language you use to talk to a server.', 'folder' => '02-linux-shell', 'status' => 'coming_soon', 'chapters' => []],
        'git-github' => ['title' => 'Git & GitHub (Advanced)', 'tagline' => 'Version control for working in a team, deep dive.', 'folder' => '03-git-github', 'status' => 'coming_soon', 'chapters' => []],
        'cicd' => ['title' => 'CI/CD (GitHub Actions)', 'tagline' => 'Push code, get automatic tests and deploys.', 'folder' => '04-cicd', 'status' => 'coming_soon', 'chapters' => []],
        'kubernetes' => ['title' => 'Kubernetes', 'tagline' => 'Managing many containers at scale.', 'folder' => '05-kubernetes', 'status' => 'coming_soon', 'chapters' => []],
        'cloud' => ['title' => 'Cloud (AWS / VPS Basics)', 'tagline' => 'Provisioning servers, domains, and deployment.', 'folder' => '06-cloud', 'status' => 'coming_soon', 'chapters' => []],
        'monitoring' => ['title' => 'Monitoring & Logging', 'tagline' => 'Knowing what is happening in production.', 'folder' => '07-monitoring', 'status' => 'coming_soon', 'chapters' => []],
        'iac' => ['title' => 'Infra as Code (Terraform/Ansible)', 'tagline' => 'Writing server setup as code.', 'folder' => '08-iac', 'status' => 'coming_soon', 'chapters' => []],
    ],
];
