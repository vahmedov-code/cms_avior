<?php
// Copy to blog.php only when enabling the module. This file contains no secrets.
return [
    'enabled' => false,
    // Existing checkout/deployment, containing index.html, style.css, blog-assets/.
    'site_root' => '/var/www/avior',
    // MUST be outside both public document roots. Contains draft images and lock.
    'private_dir' => '/var/lib/avior-blog',
    'site_url' => 'https://avior.moscow',
];
