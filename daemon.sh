CheckProcess()
{
    PROCESS_NUM=`ps -ef | grep "$1" | grep -v "grep" | wc -l`
    if [ $PROCESS_NUM -ge 1 ];
    then
        return 0
    else
        return 1
    fi
}

while true
do
    CheckProcess "/usr/bin/php-cgi"
    CheckQQ_RET=$?
    if [ $CheckQQ_RET -eq 1 ];
    then
     exec spawn-fcgi -a 127.0.0.1 -p 9000 -C 5 -f /usr/bin/php-cgi &
    fi
    sleep 1
done;