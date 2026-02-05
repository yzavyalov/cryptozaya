<?php

$login = 'check@zaya.net.ua'; // вместо from@example.com укажите адрес созданного на хостинге почтового ящика
$password = 'Zav21362080'; // вместо password укажите пароль созданного на хостинге почтового ящика
$to = '8540462@gmail.com'; // вместо to@example.com укажите адрес получателя

$text = "Привет, проверка связи по SMTP."; // содержимое отправляемого письма

// функция получения кода ответа сервера
function get_data($smtp_conn) {
    $data = "";
    while ($str = fgets($smtp_conn, 515)) {
        $data .= $str;
        if (substr($str, 3, 1) == " ") {
            break;
        }
    }
    return $data;
}

// формирование служебного заголовка письма
$header = "Date: " . date("D, j M Y G:i:s") . " +0300\r\n";
$header .= "From: =?UTF-8?Q?" . str_replace("+", "_", str_replace("%", "=", urlencode('Тестовый скрипт'))) . "?= <$login>\r\n";
$header .= "X-Mailer: Test script hosting Ukraine.com.ua \r\n";
$header .= "Reply-To: =?UTF-8?Q?" . str_replace("+", "_", str_replace("%", "=", urlencode('Тестовый скрипт'))) . "?= <$login>\r\n";
$header .= "X-Priority: 3 (Normal)\r\n";
$header .= "Message-ID: <12345654321." . date("YmjHis") . "@ukraine.com.ua>\r\n";
$header .= "To: =?UTF-8?Q?" . str_replace("+", "_", str_replace("%", "=", urlencode('Получателю тестового письма'))) . "?= <$to>\r\n";
$header .= "Subject: =?UTF-8?Q?" . str_replace("+", "_", str_replace("%", "=", urlencode('проверка'))) . "?=\r\n";
$header .= "MIME-Version: 1.0\r\n";
$header .= "Content-Type: text/plain; charset=UTF-8\r\n";
$header .= "Content-Transfer-Encoding: 8bit\r\n";

$smtp_conn = fsockopen("mail.adm.tools", 25, $errno, $errstr, 10); // соединение с почтовым сервером mail.adm.tools через порт 25
if (!$smtp_conn) { print "Соединение с сервером не прошло"; fclose($smtp_conn); exit; }
$data = get_data($smtp_conn);

fputs($smtp_conn, "EHLO ukraine.com.ua\r\n"); // начало приветствия
$code = substr(get_data($smtp_conn), 0, 3); // проверка, не вернул ли сервер ошибку
if ($code != 250) { print "Ошибка приветствия EHLO"; fclose($smtp_conn); exit; }

fputs($smtp_conn, "AUTH LOGIN\r\n"); // начало процедуры авторизации
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 334) { print "Сервер не разрешил начать авторизацию"; fclose($smtp_conn); exit; }

fputs($smtp_conn, base64_encode("$login") . "\r\n"); // отправка логина от почтового ящика (на хостинге он совпадает с именем почтового ящика)
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 334) { print "Ошибка доступа к такому пользователю"; fclose($smtp_conn); exit; }

fputs($smtp_conn, base64_encode("$password") . "\r\n"); // отправка пароля
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 235) { print "Неправильный пароль"; fclose($smtp_conn); exit; }

fputs($smtp_conn, "MAIL FROM:$login\r\n"); // отправка значения MAIL FROM
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 250) { print "Сервер отказал в команде MAIL FROM"; fclose($smtp_conn); exit; }

fputs($smtp_conn, "RCPT TO:$to\r\n"); // отправка адреса получателя
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 250 AND $code != 251) { print "Сервер не принял команду RCPT TO"; fclose($smtp_conn); exit; }

fputs($smtp_conn, "DATA\r\n"); // отправка команды DATA
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 354) { print "Сервер не принял DATA"; fclose($smtp_conn); exit; }

fputs($smtp_conn, $header . "\r\n" . $text . "\r\n.\r\n"); // отправка тела письма
$code = substr(get_data($smtp_conn), 0, 3);
if ($code != 250) { print "Ошибка отправки письма"; fclose($smtp_conn); exit; }
if ($code == 250) { print "Письмо отправлено успешно. Ответ сервера $code"; }

fputs($smtp_conn, "QUIT\r\n"); // завершение отправки командой QUIT
fclose($smtp_conn); // закрытие соединения
?>
