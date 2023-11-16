<?php

namespace App\Model;

use App\Config\Database;
use PDO;
use Exception;
use PDOException;

class Comment
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
            $connect = new PDO($dsn, $db_user, $db_pass);
            $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connect->query("SET NAMES UTF8");
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            die();
        }
        return $connect;
    }
    function addComment($title, $content)
    {
        $db = $this->dbConnect();
        $statement = $db->prepare("INSERT INTO `comments`(`title`,`content`,`user_id`) VALUES (?,?,?)");
        $return = [];

        try {
            $user_id = $_SESSION["User-Id"];
            if (empty($title) || empty($content)) {
                throw new Exception("標題或內容不能為空!!");
            }
            if ($statement->execute([$title, $content, $user_id])) {
                $return = [
                    "event" => "創建訊息",
                    "status" => "success",
                    "content" => "創建成功",
                ];
            } else {
                throw new Exception("未知錯誤");
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "創建訊息",
                "status" => "error",
                "content" => "創建失敗" . $e->getMessage(),
            ];
        } catch (Exception $e) {
            $return = [
                "event" => "創建訊息",
                "status" => "error",
                "content" => "創建失敗，" . $e->getMessage(),
            ];
        }
        return $return;
    }

    public function checkComment($comment_id)
    {
        $db = $this->dbConnect();
        $statement = $db->prepare("SELECT * FROM `comments` WHERE id=?");
        $statement->execute([$comment_id]);
        $comment = $statement->fetch(PDO::FETCH_ASSOC);
        return $comment;
    }
    public function editComment(int $comment_id, string $comment_title, string $comment_content)
    {
        $db = $this->dbConnect();
        $statement = $db->prepare("UPDATE comments SET title=?,content=? WHERE id=?");
        $return = [];

        try {
            $comment = $this->checkComment($comment_id);
            if (!$comment) {
                throw new Exception("不能編輯不存在留言!!");
            }
            if ($comment['user_id'] !== $_SESSION['User-Id']) {
                throw new Exception("不能以別人名義創建留言!!");
            }
            if (empty($comment_title) || empty($comment_content)) {
                throw new Exception("標題或內容不能為空!!");
            }
            if ($statement->execute([$comment_title, $comment_content, $comment_id])) {
                $return = [
                    "event" => "編輯訊息",
                    "status" => "success",
                    "content" => "編輯成功",
                ];
            } else {
                throw new Exception("未知錯誤");
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "編輯訊息",
                "status" => "error",
                "content" => "編輯失敗，",
            ];
        } catch (Exception $e) {
            $return = [
                "event" => "編輯訊息",
                "status" => "error",
                "content" => "編輯失敗，" . $e->getMessage(),
            ];
        }
        return $return;
    }
    public function delComment($comment_id)
    {
        $db = $this->dbConnect();
        $statement = $db->prepare("DELETE FROM comments WHERE id= ?");
        $return = [];

        try {
            $comment = $this->checkComment($comment_id);
            if (!$comment) {
                throw new Exception("不能刪除不存在留言!!");
            }
            if ($comment['user_id'] !== $_SESSION['User-Id']) {
                throw new Exception("不能以別人名義創建留言!!");
            }
            if ($statement->execute([$comment_id])) {
                $return = [
                    "event" => "刪除訊息",
                    "status" => "success",
                    "content" => "刪除成功",
                ];
            } else {
                throw new Exception("未知錯誤");
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "刪除訊息",
                "status" => "error",
                "content" => "刪除失敗",
            ];
        } catch (Exception $e) {
            $return = [
                "event" => "刪除訊息",
                "status" => "error",
                "content" => "刪除失敗，" . $e->getMessage(),
            ];
        }
        return $return;
    }

    public function getAllComment()
    {
        $db = $this->dbConnect();
        $statement = $db->prepare("SELECT * FROM comments ORDER BY id DESC");
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSelfComment($user_id)
    {
        $db = $this->dbConnect();
        $sql = "SELECT * FROM comments WHERE user_id=? ORDER BY id DESC";
        $param = [
            $user_id
        ];
        $statement = $db->prepare($sql);
        $statement->execute($param);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchTimeComment(string $search_content, string $first_time, string $last_time)
    {
        $db = $this->dbConnect();
        $now_time = date('Y-m-d H:i:s');
        $old_Time = "2020-06-27 00:00:00";
        $return = [];
        $statement = [];
        try {
            $param = "";
            if (empty($first_time) && empty($last_time)) {
                $sql = "SELECT * FROM comments  WHERE (title LIKE ? OR content LIKE ?) ORDER BY id DESC";
                $param = [
                    "%$search_content%", "%$search_content%"
                ];

                $statement = $db->prepare($sql);
            } else {
                if (empty($first_time)) {
                    $first_time = $old_Time;
                }
                if (empty($last_time)) {
                    $last_time = $now_time;
                }
                $date = date_create($first_time);
                $first_time = date_format($date, "Y-m-d H:i:s");

                $date = date_create($last_time);
                $last_time = date_format($date, "Y-m-d H:i:s");

                if (strtotime($first_time) > strtotime($last_time)) {
                    throw new Exception("起始搜尋時間比指定結束時間還要早");
                } else {
                    $sql = "SELECT * FROM comments  WHERE 
		            (title LIKE ? OR content LIKE ?)
                    AND createdAt BETWEEN ? AND ?";
                    $param = [
                        "%$search_content%", "%$search_content%", $first_time, $last_time
                    ];
                    $statement = $db->prepare($sql);
                }
            }
            if ($statement->execute($param)) {
                $data = $statement->fetchAll(PDO::FETCH_ASSOC);
                $time = $statement->rowCount();
                $return = [
                    "event" => "搜尋訊息",
                    "status" => "success",
                    "content" => "搜尋結果擁有相符共 " . $time . " 筆",
                    "statement" => $data,
                ];
            } else {
                throw new Exception("未知錯誤");
            }
        } catch (PDOException $e) {
            $return = [
                "event" => "搜尋訊息",
                "status" => "error",
                "content" => "搜尋失敗",
            ];
        } catch (Exception $e) {
            $return = [
                "event" => "搜尋訊息",
                "status" => "error",
                "content" => "搜尋失敗，" . $e->getMessage(),
                "statement" => $this->getAllComment(),
            ];
        }
        return $return;
    }
}
