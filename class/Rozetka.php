<?php
use GuzzleHttp\Client;

class Rozetka extends Base
{
    private Client $client;
    public $token;
    private $url = 'https://api-seller.rozetka.com.ua';
    public function __construct()
    {
        $this->client = new Client([
            'timeout'  => 10.0,
        ]);

        $auth = $this->auth();
        if ($auth !== null) {
            $this->token = $auth['access_token'];
        }else{
            die("Авторизація не вдалась.");
        }
    }

    public function getProducts()
    {
        $allGoods = []; // Масив для збереження всіх товарів
        $page = 1; // Починаємо з першої сторінки

        do {
            try {
                // Відправляємо запит із параметром `page`
                $response = $this->request($this->url . '/goods/all','GET', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Language' => 'uk',
                    ],
                    'query' => [
                        'page' => $page,
                        'pageSize' => 100,
                    ],
                ]);

                // Розбираємо JSON-відповідь
                $data = $response;
dd( $data['content']['items'][5]);
                // Перевірка на наявність товарів
                if (isset($data['content']['items'])) {
                    $allGoods = array_merge($allGoods, $data['content']['items']); // Додаємо товари до загального масиву
                } else {
                    echo "Немає товарів на сторінці $page";
                    break;
                }

                // Отримання інформації про пагінацію
                if (isset($data['content']['_meta'])) {
                    $totalPages = $data['content']['_meta']['pageCount'];
                    $currentPage = $data['content']['_meta']['currentPage'];
                } else {
                    break; // Виходимо з циклу, якщо немає метаданих
                }

                $page++; // Переходимо до наступної сторінки

            } catch (\Exception $e) {
                // Обробка помилок
                echo "Помилка при отриманні даних на сторінці $page: " . $e->getMessage();
                break;
            }
        } while ($page <= $totalPages);

        return $allGoods;
    }

    private function auth(){
        $authResponse = $this->request($this->url. '/sites', 'POST', [
            'json' => [
                'username' => ROZETKA_USERNAME,
                'password' => base64_encode(ROZETKA_PASSWORD)
            ]
        ]);

        // Перевірка чи успішно пройшла авторизація
        if (isset($authResponse['content']['access_token']) &&  $authResponse['success'] == true) {
            return $authResponse['content'];
        } else {
            echo "Помилка авторизації: " . json_encode($authResponse);
            return null;
        }
    }
}
