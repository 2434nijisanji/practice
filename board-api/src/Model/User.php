<?php

namespace App\Model;

use App\Config\Database;
use PDO;
use Exception;
use PDOException;

class User
{
    public function dbConnect()
    {
        $db_type = Database::DATABASE_INFO['db_type'];
        $db_host = Database::DATABASE_INFO['db_host'];
        $db_name = Database::DATABASE_INFO['db_name'];
        $db_user = Database::DATABASE_INFO['db_user'];
        $db_pass = Database::DATABASE_INFO['db_pass'];
        $dsn = $db_type . ":host=" . $db_host . ";dbname=" . $db_name;
        try {
            $db_connect = new PDO($dsn, $db_user, $db_pass);
            $db_connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db_connect->query("SET NAMES UTF8");
        } catch (PDOException $e) {
            echo "error: " . $e->getMessage();
            die();
        }
        return $db_connect;
    }
    public function findUser(string $user_id)
    {
        $db_connect = $this->dbConnect();
        $statement = $db_connect->prepare("SELECT * FROM users WHERE `id`=?");
        $user_info = [];
        try {
            $statement->execute([$user_id]);
            if ($statement->rowCount()) {
                $data = $statement->fetch(PDO::FETCH_ASSOC);
                $user_info = [
                    "account" => $data['account'],
                    "user_id" =>  $data['id'],
                    "email" => $data['email'],
                    "intro" => $data["intro"],
                ];
            } else {
                throw new Exception("未知錯誤");
            }
        } catch (PDOException $e) {
            $user_info = [
                "無此使用者",
            ];
        } catch (Exception $e) {
            $user_info = [
                $e->getMessage(),
            ];
        }
        return $user_info;
    }
    public function checkEmailName(string $account, string $email)
    {
        $db_connect = $this->dbConnect();
        $sql = "SELECT IF( EXISTS(
                            SELECT account
                            FROM users
                            WHERE account = ?), 1, 0) as name_RESULT,
                        IF( EXISTS(
                            SELECT email
                            FROM users
                            WHERE email = ?), 1, 0) as email_RESULT;";
        $statement = $db_connect->prepare($sql);
        $statement->execute([$account, $email]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }
    public function addUser(string $account, string $email, string $pass, string $pass_check)
    {
        $db_connect = $this->dbConnect();
        $sql = "INSERT INTO `users`(`account`, `email`, `password`) VALUES (?,?,?)";
        $statement = $db_connect->prepare($sql);
        $check = $this->checkEmailName($account, $email);
        $return = [];

        try {
            if (empty($account) || empty($email) || empty($pass) || empty($pass_check)) {
                throw new Exception("有欄位未填");
            }

            if ($pass !== $pass_check) {
                throw new Exception("密碼不一致");
            }

            //信箱
            //把值作為電子郵件地址來驗證
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("信箱格式錯誤" . "<br>" . "信箱範例：test@example.com");
            }

            if ($check['name_RESULT'] || $check['email_RESULT']) {
                if (($check['name_RESULT'] && $check['email_RESULT'])) {
                    throw new Exception("使用者名和信箱已被註冊");
                } elseif ($check['name_RESULT']) {
                    throw new Exception("使用者名已被註冊");
                } else {
                    throw new Exception("信箱已被註冊");
                }
            }
            $pass = password_hash($pass, PASSWORD_DEFAULT);
            if ($statement->execute([$account, $email, $pass])) {
                $return = [
                    "event" => "註冊成功",
                    "status" => "success",
                    "content" => "已註冊 # $account ，再請登入",
                ];
            } else {
                throw new Exception("未知錯誤" . $statement->errorInfo()[2]);
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "註冊失敗",
                "status" => "error",
                "content" => "註冊失敗，原因： " . $e->getMessage(),
            ];
            http_response_code(500);
            return $return;
        } catch (Exception $e) {
            $return = [
                "event" => "註冊失敗",
                "status" => "error",
                "content" => "註冊失敗，原因： " . $e->getMessage(),
            ];
            http_response_code(400);
            return $return;
        }
        http_response_code(201);
        return $return;
    }
    public function editUser(
        string $account,
        string $email,
        string $intro,
        string $pass,
        string $pass_check
    ) {

        $return = [];
        try {
            if ($pass !== $pass_check) {
                throw new Exception("密碼不一致");
            }

            //信箱
            //把值作為電子郵件地址來驗證
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("信箱格式錯誤" . "<br>" . "信箱範例：test@example.com");
            }
            $data = [
                "account" => $account,
                "email" => $email,
            ];
            $user_id = $_SESSION["User-Id"];
            $user_data = $this->findUser($user_id);
            $check_data = [];
            foreach ($data as $key => $value) {
                if ($value !== $user_data[$key]) {
                    $check_data[$key] = $value;
                } else {
                    $check_data[$key] = "";
                }
            }
            $check = $this->checkEmailName($check_data['account'], $check_data['email']);

            if ($check['name_RESULT'] || $check['email_RESULT']) {
                if (($check['name_RESULT'] && $check['email_RESULT'])) {
                    throw new Exception("使用者名和信箱已被註冊");
                } elseif ($check['name_RESULT']) {
                    throw new Exception("使用者名已被註冊");
                } else {
                    throw new Exception("信箱已被註冊");
                }
            }

            $db_connect = $this->dbConnect();
            $replace = [];
            if (empty($pass)) {
                $sql = "UPDATE `users` SET `account`=?, `email`=?, `intro`=? WHERE `id` = ?";
                $statement = $db_connect->prepare($sql);
                $replace = [$account, $email, $intro, $user_id];
            } else {
                $sql = "UPDATE `users` SET `account`=?, `email`=?, `intro`=?, `password`=? WHERE `id` = ?";
                $statement = $db_connect->prepare($sql);
                $pass = password_hash($pass, PASSWORD_DEFAULT);
                $replace = [$account, $email, $intro, $pass, $user_id];
            }
            
            if ($statement->execute($replace)) {
                $return = [
                    "event" => "修改成功",
                    "status" => "success",
                    "content" => "已成功修改 # $account ，再請重新登入",
                ];
            } else {
                throw new Exception("未知錯誤" . $statement->errorInfo()[2]);
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "修改失敗",
                "status" => "error",
                "content" => "修改失敗，原因： " . $e->getMessage(),
            ];
            http_response_code(500);
            return $return;
        } catch (Exception $e) {
            $return = [
                "event" => "修改失敗",
                "status" => "error",
                "content" => "修改失敗，原因： " . $e->getMessage(),
            ];
            http_response_code(400);
            return $return;
        }
        http_response_code(201);
        return $return;
    }
    public function userLogin(string $account, string $pass)
    {
        $db_connect = $this->dbConnect();
        $statement = $db_connect->prepare("SELECT * FROM users WHERE `account`=?");
        $statement->execute([$account]);
        $return = [];
        try {
            if (empty($account) || empty($pass)) {
                throw new Exception("有欄位未填!!");
                //確認有沒有帳號
            }
            if (!$statement->rowCount()) {
                throw new Exception("帳號名或密碼錯誤");
            } else {
                $data = $statement->fetch(PDO::FETCH_ASSOC);
                $password_hash = $data['password'];
                $pass = password_verify($pass, $password_hash);

                if ($pass) {
                    $_COOKIE['X-User-Id'] = $data['id'];
                    $_SESSION["User-Id"] = $data['id'];
                    $return = [
                        "account" => $account,
                        "user_id" => $data['id'],
                        "email" => $data['email'],
                        "intro" => $data['intro'],
                        "event" => "登入訊息",
                        "status" => "success",
                        "content" => "登入成功，歡迎 $account 登入",
                    ];
                } else {
                    throw new Exception("帳號名或密碼錯誤");
                }
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "登入訊息",
                "status" => "error",
                "content" => "登入失敗",
            ];
            http_response_code(500);
            return $return;
        } catch (Exception $e) {
            $return = [
                "event" => "登入訊息",
                "status" => "error",
                "content" => "登入失敗，" . $e->getMessage(),
            ];
            http_response_code(400);
            return $return;
        }
        http_response_code(200);
        return $return;
    }
}
