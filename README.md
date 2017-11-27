# talium-test

Для запуска использовался apache2 c конфигом:

    <VirtualHost *:80>
    ...
        ProxyRequests off
        ProxyTimeout 10
        
        ProxyPass /ws ws://localhost:3334/
        ProxyPassReverse /ws ws://localhost:3334/
     ...
    </VirtualHost>

Для запуска приложение необходимо выполнить 
    
    php /folder/server-start.php
   