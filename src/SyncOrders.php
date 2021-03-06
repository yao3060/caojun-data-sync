<?php


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
    $body = $response->getBody()->getContents();

    (new LogFactory)->make('app')->info($body);

    return json_decode($body);
}

function getHuaRunOpenAPISign($requestData, $timeStamp): string
{
    $signData = sprintf(
        'Api_ID=%s&Api_Version=%s&App_Pub_ID=%s&App_Sub_ID=%s&App_Token=%s&Format=%s&Partner_ID=%s&REQUEST_DATA=%s&Sign_Method=%s&Sys_ID=%s&Time_Stamp=%s&%s',
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
        $timeStamp,
        $_ENV['HUARUN_OPEN_API_SIGN_TOKEN']
    );
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
    //     return "YFK"; //YFK?????????????????????
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
        "cashierId" =>  $_ENV['HUARUN_OPEN_API_CASHIER_ID'], // ???????????????
        "checkCode" => "88888888", // ??????????????????(??????) ???IMPOS??????
        "itemList" => [],
        "mall" => $_ENV['HUARUN_OPEN_API_MALL'], // ????????????
        "orderId" =>  $order->orderNo, // ?????????
        "payList" => [
            [
                "discountAmt" => 0,
                "payAmt" =>  $order->payAmt,
                "paymentMethod" =>  getHuaRunPaymentType($order->payType),
                // yyyyMMddhhmmss???????????????????????????48??????????????????
                "time" =>  str_replace([" ", "-", ":"], "", $order->mealTm),
                "value" => $order->payAmt
            ]
        ],
        "source" => $_ENV['HUARUN_OPEN_API_POS_ID'],
        "store" => $_ENV['HUARUN_OPEN_API_STORE_ID'], // ????????????
        "tillId" => $_ENV['HUARUN_OPEN_API_POS_ID'], // ??????????????????IMPOS??????
        "time" =>  date('YmdHis'),
        "totalAmt" => $order->payAmt, //???????????????: "?????????????????????, ?????????????????????"
        "type" => getHuaRunOrderType($order->orderType), // ????????????, ?????????SALE, ?????????ONLINEREFUND
    ];
    $timeStamp = (new DateTime())->format('Y-m-d H:i:s:v');
    $requestJson = [
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
        ],
        "REQUEST_DATA" => $requestData,
    ];
    // print_r($requestJson);
    $response = $client->request(
        'POST',
        'rs-service/',
        ['json' => ["REQUEST" => $requestJson]]
    );
    $result = json_decode($response->getBody()->getContents());

    if ($result->RETURN_DATA->header->errcode !== '0000') {
        (new LogFactory)->make('sync-orders')->warning('sync order failed, ' . $result->RETURN_DATA->header->errmsg);
    }
    (new LogFactory)->make('app')->info($result->RETURN_DATA->header->errmsg);
}

function sync2Dist()
{
    $data = getOrdersFromFuYou();

    if ($data->totalCount === 0) {
        echo "Nothing to Sync";
        exit();
    }

    $client = new \GuzzleHttp\Client(['base_uri' => $_ENV['HUARUN_API_BASE_URL']]);
    $logger = (new LogFactory)->make('app');
    foreach ($data->data as $key => $order) {
        $logger->info('??????????????????:' . $order->orderNo);
        sendOrder2HuaRun($order, $client);
    }
    $logger->info('?????????');
}
