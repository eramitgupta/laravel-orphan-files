# README demo for erag/laravel-orphan-files.
#
# Regenerate from the package root (needs vhs, ttyd and ffmpeg):
#   vhs art/demo.tape      (or: composer demo)
#
# Every command below is the real package, running in a throwaway Testbench
# app (art/) seeded with 23 referenced and 20 orphaned images.
#
# The README GIF itself was recorded with the same settings on a MinIO bucket
# (320 images, 1.27 GB); that S3 setup is not part of the package.

Output art/demo.gif

Require php

Set Shell "bash"
# Colours from the docs dark mode: background is --vp-code-block-bg (#111113), text is
# --vp-c-text-1 (#dfdfd6), prompt, cursor and INFO badges use the brand reds (#ff6a4d, #f53003).
Set Theme {"name": "Orphan Files Dark", "background": "#111113", "foreground": "#dfdfd6", "selection": "#2e2e32", "cursor": "#ff6a4d", "black": "#111113", "red": "#ff6a4d", "green": "#4ade80", "yellow": "#facc15", "blue": "#f53003", "purple": "#c084fc", "cyan": "#22d3ee", "white": "#ffffff", "brightBlack": "#98989f", "brightRed": "#ff8a73", "brightGreen": "#86efac", "brightYellow": "#fde047", "brightBlue": "#ff6a4d", "brightPurple": "#d8b4fe", "brightCyan": "#67e8f9", "brightWhite": "#ffffff"}
Set FontSize 18
Set Width 1320
Set Height 600
Set Padding 24
Set Framerate 12
Set TypingSpeed 40ms

Hide
Type "cd art && php seed.php"
Enter
Wait
Type `export PS1='\[\e[38;2;255;106;77m\]> \[\e[0m\]' && clear`
Enter
Wait
Type "php artisan orphan:scan"
Show

Sleep 900ms
Enter
Wait
Sleep 2.2s

Hide
Type "clear"
Enter
Wait
Show

Type "php artisan orphan:clean"
Sleep 300ms
Enter
Wait
Sleep 2.8s

Hide
Type "clear"
Enter
Wait
Show

Type "php artisan orphan:clean --force"
Sleep 300ms
Enter
Wait
Sleep 2.2s

Hide
Type "clear"
Enter
Wait
Show

Type "php artisan orphan:scan"
Sleep 300ms
Enter
Wait
Sleep 2.6s
