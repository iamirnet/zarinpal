<?php namespace iAmirNet\Zarinpal;

class ZarinpalClient
{
    public $merchant = "";
    public $version = "";

    public static function fast($merchant, $version = "2024-04-26")
    {
        return (new static($merchant, $version));
    }
    public function __construct($merchant, $version = "2024-04-26")
    {
        $this->merchant = $merchant;
        $this->version = $version;
    }

    public function pay($amount, $redirect, $factorId, $card = null, $name = null, $mobile = null, $email = null, $description = null)
    {
        if (empty($redirect))
            return ['status' => false, 'code' => -2, 'result' => "لینک بازگشت ( CallbackURL ) نباید خالی باشد"];
        $data = [
            "amount" => (int)$amount,
            "callback_url" => $redirect,
            "description" => $description?:"Pay Order #{$factorId}",
            "order_id" => $factorId."-".time(),
        ];
        if ($mobile) $data['mobile'] = $mobile;
        if ($email) $data['mobile'] = $email;
        $result = $this->post("request", $data);
        if (isset($result['data']['code']) && $result['data']['code'] == 100) {
            $response = ["status" => true];
            $response["code"] = $result['data']['code'];
            $response["message"] = $result['data']['message'];
            $response["tran_id"] = $result['data']['authority'];
            $response["url"] = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"];
            $response["fee"] = $result['data']['fee'];
        } else {
            $response = ["status" => false];
            $response["code"] = isset($result['data']['code']) ? $result['data']['code'] : -100000;
            $response["message"] = static::error($response["code"]);
        }
        return $response;
    }

    public function verify($authority, $amount)
    {
        $data = [
            "authority" => $authority,
            "amount" => $amount,
        ];
        $result = $this->post("verify", $data);
        if (isset($result['data']['code']) && $result['data']['code'] == 100) {
            $response = ["status" => true];
            $response["code"] = $result['data']['code'];
            $response["message"] = $result['data']['message'];
            $response["card_hash"] = $result['data']['card_hash'];
            $response["card"] = $result['data']['card_pan'];
            $response["pay_id"] = $result['data']['ref_id'];
            $response["authority"] = $authority;
            $response["fee"] = $result['data']['fee'];
        } else {
            $response = ["status" => false];
            $response["code"] = isset($result['data']['code']) ? $result['data']['code'] : -100000;
            $response["message"] = static::error($response["code"]);
        }
        return $response;
    }

    public function post($method, $data)
    {
        $data["merchant_id"] = $this->merchant;
        $jsonData = json_encode($data);
        $ch = curl_init("https://api.zarinpal.com/pg/v4/payment/{$method}.json");
        curl_setopt($ch, CURLOPT_USERAGENT, 'iAmir.net ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return ['data' => [], 'errors' => ['code' => "-10000"]];
        } else {
            return json_decode($result, true);
        }
    }

    public static function error($code)
    {
        $translations = [
            '100' => 'تراکنش با موفقیت انجام گردید',
            '101' => 'عمليات پرداخت موفق بوده و قبلا عملیات وریفای تراكنش انجام شده است',
            '-9' => 'خطای اعتبار سنجی',
            '-10' => 'ای پی و يا مرچنت كد پذيرنده صحيح نمی باشد',
            '-11' => 'مرچنت کد فعال نیست لطفا با تیم پشتیبانی ما تماس بگیرید',
            '-12' => 'تلاش بیش از حد در یک بازه زمانی کوتاه',
            '-15' => 'ترمینال شما به حالت تعلیق در آمده با تیم پشتیبانی تماس بگیرید',
            '-16' => 'سطح تاييد پذيرنده پايين تر از سطح نقره ای می باشد',
            '-30' => 'اجازه دسترسی به تسویه اشتراکی شناور ندارید',
            '-31' => 'حساب بانکی تسویه را به پنل اضافه کنید مقادیر وارد شده برای تسهیم صحيح نمی باشد',
            '-32' => 'مقادیر وارد شده برای تسهیم صحيح نمی باشد',
            '-33' => 'درصد های وارد شده صحيح نمی باشد',
            '-34' => 'مبلغ از کل تراکنش بیشتر است',
            '-35' => 'تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است',
            '-40' => 'پارامترهای اضافی نامعتبر، expire_in معتبر نیست',
            '-50' => 'مبلغ پرداخت شده با مقدار مبلغ در وریفای متفاوت است',
            '-51' => 'پرداخت ناموفق',
            '-52' => 'خطای غیر منتظره با پشتیبانی تماس بگیرید',
            '-53' => 'اتوریتی برای این مرچنت کد نیست',
            '-54' => 'اتوریتی نامعتبر است',
        ];
        $unknownError = 'خطای ناشناخته رخ داده است. در صورت کسر مبلغ از حساب حداکثر پس از 72 ساعت به حسابتان برمیگردد';

        return array_key_exists($code, $translations) ? $translations[$code] : $unknownError;
    }
}
