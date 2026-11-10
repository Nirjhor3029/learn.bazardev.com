<?php

return [
    // Sibling repo that holds the chapter markdown files (readable on GitHub too).
    'content_path' => env('COURSE_CONTENT_PATH', base_path('../devops-for-developers')),

    'github_repo' => 'https://github.com/Nirjhor3029/devops-for-developers',

    'courses' => [
        'docker' => [
            'title' => 'Docker',
            'tagline' => 'Container ki, keno lage, ar nijer app Dockerize kora — shuru theke shesh.',
            'folder' => '01-docker',
            'status' => 'available',
            'chapters' => [
                '01-what-is-docker' => ['title' => 'Docker ki, ar keno lage', 'status' => 'available'],
                '02-install-and-first-container' => ['title' => 'Install + first container', 'status' => 'coming_soon'],
                '03-images-vs-containers' => ['title' => 'Images vs Containers', 'status' => 'coming_soon'],
                '04-dockerfile' => ['title' => 'Dockerfile — nijer image lekha', 'status' => 'coming_soon'],
                '05-volumes-and-networking' => ['title' => 'Volumes & Networking', 'status' => 'coming_soon'],
                '06-docker-compose' => ['title' => 'Docker Compose — multi-container app', 'status' => 'coming_soon'],
                '07-best-practices-debugging' => ['title' => 'Best practices + debugging', 'status' => 'coming_soon'],
                '08-real-project-laravel-dockerize' => ['title' => 'Real project: Laravel app Dockerize', 'status' => 'coming_soon'],
            ],
        ],
        'linux-shell' => ['title' => 'Linux & Shell Basics', 'tagline' => 'Server er sathe kotha bolar bhasha.', 'folder' => '02-linux-shell', 'status' => 'coming_soon', 'chapters' => []],
        'git-github' => ['title' => 'Git & GitHub (Advanced)', 'tagline' => 'Team e kaj korar jonno version control, deep dive.', 'folder' => '03-git-github', 'status' => 'coming_soon', 'chapters' => []],
        'cicd' => ['title' => 'CI/CD (GitHub Actions)', 'tagline' => 'Push korle automatic test + deploy.', 'folder' => '04-cicd', 'status' => 'coming_soon', 'chapters' => []],
        'kubernetes' => ['title' => 'Kubernetes', 'tagline' => 'Onek container ke scale e manage kora.', 'folder' => '05-kubernetes', 'status' => 'coming_soon', 'chapters' => []],
        'cloud' => ['title' => 'Cloud (AWS / VPS Basics)', 'tagline' => 'Server provision, domain, deploy.', 'folder' => '06-cloud', 'status' => 'coming_soon', 'chapters' => []],
        'monitoring' => ['title' => 'Monitoring & Logging', 'tagline' => 'Production e ki hocche, bujhte parar upay.', 'folder' => '07-monitoring', 'status' => 'coming_soon', 'chapters' => []],
        'iac' => ['title' => 'Infra as Code (Terraform/Ansible)', 'tagline' => 'Server setup ke code diye likhe rakha.', 'folder' => '08-iac', 'status' => 'coming_soon', 'chapters' => []],
    ],
];
