#!/usr/bin/env sh
while :
do
php SpelakoOne.php --core="../SpelakoCore/SpelakoCore.php" --config="config.json" --host="http://127.0.0.1:5700"
echo "Waiting for 10 seconds, press Ctrl+C to quit ..."; sleep 10
done