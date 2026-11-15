# README demo for erag/laravel-orphan-storage.
#
# Regenerate from the package root (needs vhs, ttyd and ffmpeg):
#   vhs art/demo.tape      (or: composer demo)
#
# Every command below is the real package, running in a throwaway Testbench
# app (art/) seeded with 23 referenced and 20 orphaned images.

Output art/demo.gif

Require php

Set Shell "bash"
Set Theme "Dracula"
Set FontSize 18
Set Width 1320
Set Height 600
Set Padding 16
Set Framerate 12
Set TypingSpeed 40ms

Hide
Type "cd art && php seed.php && clear"
Enter
Wait
Show

Sleep 200ms
Type "php artisan orphan:scan"
Sleep 300ms
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
