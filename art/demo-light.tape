# Light-mode demo GIF for the erag/laravel-orphan-files docs.
#
# Regenerate from the package root (needs vhs, ttyd and ffmpeg):
#   vhs art/demo-light.tape
#
# Same commands as art/demo.tape, in the docs light-mode colours. The output goes
# straight to the docs site (dev-vue/docs), which shows it only in light mode; the
# README keeps the dark art/demo.gif.
#
# Every command below is the real package, running in a throwaway Testbench
# app (art/) seeded with 23 referenced and 20 orphaned images.
#
# The published light GIF was recorded with the same settings on a MinIO bucket
# (320 images, 1.27 GB); that S3 setup is not part of the package.

Output "../docs/laravel-orphan-files/docs/public/demo-light.gif"

Require php

Set Shell "bash"
# Colours from the docs light mode: background is --vp-code-block-bg (#f6f6f7), text is
# --vp-c-text-1 (#3c3c43), prompt, cursor and INFO badges use the brand reds (#e0310a, #f53003).
Set Theme {"name": "Orphan Files Light", "background": "#f6f6f7", "foreground": "#3c3c43", "selection": "#e2e2e3", "cursor": "#e0310a", "black": "#18181b", "red": "#e0310a", "green": "#15803d", "yellow": "#ca8a04", "blue": "#f53003", "purple": "#7e22ce", "cyan": "#0e7490", "white": "#ffffff", "brightBlack": "#67676c", "brightRed": "#c82c09", "brightGreen": "#166534", "brightYellow": "#a16207", "brightBlue": "#c82c09", "brightPurple": "#6b21a8", "brightCyan": "#155e75", "brightWhite": "#ffffff"}
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
Type `export PS1='\[\e[38;2;224;49;10m\]> \[\e[0m\]' && clear`
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
