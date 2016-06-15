smsc
-----
PHP library for yii2. Make things easier if you use smsc.ru service for sending sms.

Installation
-----
```
composer require misterlexa/smsc "*@dev"
```
Modify YII2 config file like this:
```
  'components' => [
      ...
        'smsc' => [
            'class' => 'smsc\Sender',
            'login' => 'yourlogin',
            'pass' => 'your_pass',
            'is_post' => 0,
            'is_https' => 0,
            'charset' => 'utf8',
            'debug' => 1,
            'from' => 'api@smsc.ru',
        ],
      ...
  ],
  'aliases' => [
        '@smsc' => '@vendor/misterlexa/smsc',
  ],

```
Usage
-----
```
class MyClass extends ActiveController {
    public function hello()
    {
        //Send sms
        $res = Yii::$app->smsc->sendSms('+79130000000','Your pass is 87643');
        
        //Get your account balance
        $res = Yii::$app->smsc->getBalance();
        
        //Get sms status
        $res = Yii::$app->smsc->getStatus(6,89130664360);
    }
```
