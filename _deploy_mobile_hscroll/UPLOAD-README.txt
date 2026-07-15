Upload these files to LIVE (same folder structure):

1. public/css/app-custom.css
2. public/js/core/datatable-helper.js
3. resources/views/layouts/app.blade.php

Then on live (SSH or cPanel Terminal):
  php artisan view:clear
  php artisan cache:clear

Phone: hard refresh / clear site data.

Verify CSS loaded: View Source should show:
  app-custom.css?v=20260715-hscroll2
