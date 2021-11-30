<?php
date_default_timezone_set('Asia/Shanghai');

require __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


function getOrdersSign(): string
{
    return md5(
        $_ENV['FUIOU_MCHNT_CD'] . '|' .
            $_ENV['FUIOU_API_SECRET'] . '|' .
            $_ENV['FUIOU_SHOP_ID'] . '|' .
            $_ENV['FUIOU_SALT']
    );
}

function getOrdersFromFuYou(): object
{
    $client   = new \GuzzleHttp\Client(['base_uri' => $_ENV['FUIOU_API_BASE_URL']]);
    $response = $client->request(
        'POST',
        'queryOrderInfoList.action',
        [
            'json' => [
                'mchntCd'       => $_ENV['FUIOU_MCHNT_CD'],
                'key'           => getOrdersSign(),
                'shopId'        => $_ENV['FUIOU_SHOP_ID'],
                'payTmStartStr' => date(
                    'Y-m-d H:i:s',
                    mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"))
                ),
                'payTmEndStr'   => date(
                    'Y-m-d H:i:s',
                    mktime(
                        0,
                        0,
                        0,
                        date("m"),
                        date("d"),
                        date("Y")
                    )
                ),
            ],
        ]
    );
    $body     = $response->getBody()->getContents();

    error_log($body, 3, __DIR__ . '/logs/orders.json');
    return json_decode($body);
}

function getHuaRunOpenAPISign($requestData, $timeStamp): string
{
    $signData = sprintf(
        'Api_ID=%s&Api_Version=%s&App_Pub_ID=%s&App_Sub_ID=%s&App_Token=%s&Format=%s&Partner_ID=%s&REQUEST_DATA=%s&Sign_Method=%s&Sys_ID=%s&Time_Stamp=%s',
        $_ENV['HUARUN_OPEN_API_ID'],
        $_ENV['HUARUN_OPEN_API_VERSION'],
        $_ENV['HUARUN_OPEN_API_APP_PUB_ID'],
        $_ENV['HUARUN_OPEN_API_APP_SUB_ID'],
        $_ENV['HUARUN_OPEN_API_APP_TOKEN'],
        $_ENV['HUARUN_OPEN_API_FORMAT'],
        $_ENV['HUARUN_OPEN_API_PARTNER_ID'],
        json_encode($requestData),
        $_ENV['HUARUN_OPEN_API_SIGN_METHOD'],
        $_ENV['HUARUN_OPEN_API_SYS_ID'],
        $timeStamp
    );
    error_log("HuaRun OpenAPI Sign String:" . $signData . PHP_EOL, 3, __DIR__ . '/logs/app.log');
    return strtoupper(md5($signData));
}

function getHuaRunPaymentType(string $type)
{
    if ($type === "CASH") {
        return "CH";
    }

    if (in_array($type, ["LETPAY", "WECHAT", "JSAPI"])) {
        return "WP";
    }

    if (in_array($type, ['ALIPAY', 'FWC'])) {
        return "AP";
    }

    // if ($type === "YFK") {
    //     return "YFK"; //YFK会员卡余额支付
    // }

    return 'OT';
}

function getHuaRunOrderType($type)
{
    if ($type == "99" || $type == "00") {
        return 'ONLINEREFUND';
    }

    return 'SALE';
}

function sendOrder2HuaRun($order, \GuzzleHttp\Client $client)
{
    $requestData = [
        "cashierId" =>  $order->cashierId, // 收银员编号
        "checkCode" => "88888888", // 店铺验证密钥(密码) 由IMPOS提供
        "itemList" => [],
        "mall" => $_ENV['HUARUN_OPEN_API_MALL'], // 商场编号
        "orderId" =>  $order->orderNo, // 订单号
        "payList" => [
            [
                "discountAmt" => 0,
                "payAmt" =>  $order->payAmt,
                "paymentMethod" =>  getHuaRunPaymentType($order->payType),
                // yyyyMMddhhmmss（此接口仅支持接收48天内的订单）
                "time" =>  str_replace([" ", "-", ":"], "", $order->mealTm),
                "value" => $order->payAmt
            ]
        ],
        "source" => $_ENV['HUARUN_OPEN_API_POS_ID'],
        "store" => $order->shopId, // 店铺编号
        "tillId" => $_ENV['HUARUN_OPEN_API_POS_ID'], // 收银机编号由IMPOS提供
        "time" =>  date('YmdHis'),
        "totalAmt" => $order->payAmt, //订单总金额: "正数：销售订单, 负数：退货订单"
        "type" => getHuaRunOrderType($order->orderType), // 订单类型, 销售：SALE, 退款：ONLINEREFUND
    ];
    $timeStamp = (new DateTime())->format('Y-m-d H:i:s:v');
    $requestJson = [
        "REQUEST_DATA" => $requestData,
        "HRT_ATTRS" => [
            "App_ID" => $_ENV['HUARUN_OPEN_API_APP_ID'],
            "Partner_ID" => $_ENV['HUARUN_OPEN_API_PARTNER_ID'],
            "Api_Version" => $_ENV['HUARUN_OPEN_API_VERSION'],
            "App_Sub_ID" => $_ENV['HUARUN_OPEN_API_APP_SUB_ID'],
            "Format" => $_ENV['HUARUN_OPEN_API_FORMAT'],
            "Time_Stamp" => $timeStamp,
            "Api_ID" => $_ENV['HUARUN_OPEN_API_ID'],
            "App_Token" => $_ENV['HUARUN_OPEN_API_APP_TOKEN'],
            "App_Pub_ID" => $_ENV['HUARUN_OPEN_API_APP_PUB_ID'],
            "Sign_Method" => $_ENV['HUARUN_OPEN_API_SIGN_METHOD'],
            "Sign" => getHuaRunOpenAPISign($requestData, $timeStamp),
            "Sys_ID" => $_ENV['HUARUN_OPEN_API_SYS_ID']
        ]
    ];
    print_r($requestJson);
    $response = $client->request(
        'POST',
        'rs-service/',
        ['json' => ["REQUEST" => $requestJson]]
    );
    $result = json_decode($response->getBody()->getContents());
    print_r($result);
    die;
}

function sync2Dist()
{
    $data = getOrdersFromFuYou();

    if ($data->totalCount === 0) {
        echo "Nothing to Sync";
        exit();
    }

    $client = new \GuzzleHttp\Client(['base_uri' => $_ENV['HUARUN_API_BASE_URL']]);
    foreach ($data->data as $key => $order) {
        echo "订单号" . ($key + 1) . ": " . $order->orderNo . PHP_EOL;
        sendOrder2HuaRun($order, $client);
    }
}

sync2Dist();
