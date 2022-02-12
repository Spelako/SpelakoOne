@echo off
title SpelakoOne
:start
php SpelakoOne.php --core="../SpelakoCore/SpelakoCore.php" --config="config.json" --host="http://127.0.0.1:5700"
goto start