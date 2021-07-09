<?php
/**
 * SQLite3データベースを操作する簡易JSONサーバーです。
 * 
 */

/*
------------------------------------------------------
　設定
------------------------------------------------------
*/

define('MIGRATE_FILENAME', 'migrate.json');    // マイグレーションファイルの名前
define('DB_FILENAME',      'db.sqlite3');      // SQLite3のデータベースファイルの名前
define('BASE_DIRNAME',     'jsonphp');         // 上記のファイルを格納するディレクトリ名
define('EXPIRATION',       30);                // トークンの有効期限（分）
// 次のdefineをコメントアウトすると自動的にホームディレクトリの下の jsonphp　を利用
// さくらのレンタルサーバー対策
define('HOME',             '/var/www/dev/json/' . BASE_DIRNAME . '/');

// 認証情報
// 1行に付き1件のログイン情報を書いてください。パスワード平文で申し訳ない。
define('AUTH', [
    ["username" => "test-user", "password" => "PassWord"],
]);





/**
 * 初期設定
 */
ini_set('display_errors', 0);
header('Content-type: application/json; charset=utf-8');

if (!defined('HOME')) {
    // 定数HOMEが設定されていなければ、さくらのレンタルサーバー用のものを設定する
    $path_elements = explode('/', trim($_SERVER['DOCUMENT_ROOT'], '/'));
    $home = '/' . $path_elements[0] . '/' . $path_elements[1] . '/' . BASE_DIRNAME . '/';
    difine('HOME', $home);
}

/**
 * return_json
 * 
 * JSONを出力してスクリプトを終了する
 * 
 * @param bool $result
 * @param mix $messageOrData $result==trueの時はデータを、$result==falseの時はエラーメッセージを渡す
 */

function return_json(bool $result, $messageOrData) {
    echo json_encode([
        'result' => $result,
        $result ? 'data' : 'message' => $messageOrData
    ], true);
    exit;
}

/**
 * is_column_name_valid
 * 
 * @param string $name
 * @return bool
 */
function is_column_name_valid(string $name) {
    return !!preg_match('/^[a-z0-9_]{1,100}$/i', $name);
}

/**
 * table_exists
 * データベース内にテーブルが存在するか
 * 
 * @param string $table_name
 * @return bool
 */
function table_exists($table_name) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT count(name) AS `count` FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table_name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row['count'] > 0;
}

/**
 * is_vector
 * 通常の配列かどうか
 * 
 * @param array $arr
 * @return bool
 */
function is_vector(array $arr) {
    return array_values($arr) === $arr;
}

/**
 * decompose_input_data
 * 入力データをカラムの配列と値の配列に分解します
 * @param array $data
 * @param array $columns 参照形式
 * @param array $values  参照形式
 */
function decompose_input_data(&$columns, &$values) {
    $data = json_decode(filter_input(INPUT_POST, 'data'), true);
    if (!$data) {
        return;
    }

    $columns = [];
    $values  = [];
    foreach ($data as $column => $value) {
        if (!is_column_name_valid($column)) {
            return_json(false, "カラム名「{$column}」が正しくありません");
        }
        if (is_array($value)) {
            return_json(false, "カラム「{$column}」のデータが正しくありません");
        }
        $columns[] = $column;
        $values[] = $value;
    }
}

/**
 * exec_query
 * SQLを発行してJSONを出力します。
 * 
 * @param string $sql
 * @param array $values
 */
function exec_query($sql, $values) {
    global $pdo;

    $rows = [];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        if (substr($sql, 0, 6) === 'SELECT') {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    catch(PDOException $e) {
        $pdo->rollBack();
        return_json(false, "SQLエラー：" . $e->getMessage());
    }
    $pdo->commit();
    return_json(true, $rows);
}

/**
 * add_token
 * tokenをDBに格納
 * 
 * @param string $token
 */
function add_token($token) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('INSERT INTO jsonphp_sessions (token,stamp) VALUES (?,?)');
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }
}

/**
 * is_token_valid
 * tokenが有効かどうか。有効ならトークンの更新もする
 * 
 * @return bool
 */
function is_token_valid() {
    global $pdo;

    $token = filter_input(INPUT_POST, 'token');
    if (!$token) {
        return false;
    }

    $valid = false;
    try {
        $stmt = $pdo->prepare('SELECT stamp FROM jsonphp_sessions WHERE token=?');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['stamp'] >= date('Y-m-d H:i:s', time() - EXPIRATION * 60)) {
            $valid = true;
        }
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }

    if ($valid) {
        try {
            $pdo->beginTransaction();
            // 存在するなら更新する
            $stmt = $pdo->prepare("UPDATE jsonphp_sessions SET stamp=? WHERE token=?");
            $stmt->execute([date('Y-m-d H:i:s'), $token]);
        }
        catch(PDOException $e) {
            $pdo->rollBack();
            return_json(false, "SQLエラー：" . $e->getMessage());
        }
        $pdo->commit();
    }
    return $valid;
}

/**
 * delete_old_token
 * 有効期限切れのトークンを削除する
 */
function delete_old_token() {
    global $pdo;

    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM jsonphp_sessions ".
            "WHERE stamp<'" . date('Y-m-d H:i:s', time() - EXPIRATION * 60) . "'");
    }
    catch(PDOException $e) {
        // 例外は握りつぶす
        $pdo->rollBack();
    }
    $pdo->commit();
}






// コマンドを取得
$command = strtoupper(filter_input(INPUT_GET, 'cmd'));

/**
 * PDO
 */
try {
    $pdo = new \PDO('sqlite:' . HOME . DB_FILENAME);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    return_json(false, "PDOエラー：" . $e->getMessage());
}

/**
 * マイグレーション
 */
if ($command === 'MIGRATE') {
    if (!file_exists(HOME . MIGRATE_FILENAME)) {
        // 指定のマイグレーションファイルが無い
        return_json(false, 'マイグレーションファイルがありません');
    }

    $migrate = file_get_contents(HOME . MIGRATE_FILENAME);
    $table_defs = json_decode($migrate, true);
    if (!$table_defs) {
        // 正しくJSONでコードできない
        return_json(false, '正しいマイグレーションファイルではありません');
    }

    foreach ($table_defs as $table => $columns) {
        if (table_exists($table)) {
            // テーブルが存在していたら、何もしない
            continue;
        }
        $column_defs = [];
        foreach ($columns as $name => $type) {
            $type = strtoupper($type);
            if ($type !== 'TEXT' && $type !== 'INTEGER' && $type !== 'KEY' && $type !== 'REAL') { //BLOBは外す
                // typeが正しくない
                return_json(false, "テーブル：$table カラム：$name のカラムタイプが正しくありません。");
            }
            if (!is_column_name_valid($name)) {
                // nameが正しくない（英数+アンダースコア1～100文字）
                return_json(false, "テーブル：$table カラム：$name のカラム名が正しくありません。");
            }
            if ($type === 'KEY') {
                $column_defs[] = $name . ' INTEGER PRIMARY KEY AUTOINCREMENT';
            }
            else {
                $column_defs[] = $name . ' ' . $type;
            }
        }
        $sql = 'CREATE TABLE ' . $table . ' (' . implode(',', $column_defs) . ')';
        try {
            $pdo->exec($sql);
        }
        catch(PDOException $e) {
            return_json(false, "SQLエラー：" . $e->getMessage());
        }
    }

    // 管理テーブルの作成
    try {
        if (!table_exists('jsonphp_sessions')) {
            $pdo->exec('CREATE TABLE jsonphp_sessions (token TEXT UNIQUE, stamp TEXT)');
        }
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }

    return_json(true, []);
}
// 認証
elseif ($command === 'AUTH') {
    $username = filter_input(INPUT_POST, 'username');
    $password = filter_input(INPUT_POST, 'password');
    
    if (!$username || !$password) {
        return_json(false, 'usernameまたはpasswordが指定されていません');
    }
    foreach (AUTH as $auth) {
        if (!isset($auth['username']) || !isset($auth['password'])) {
            continue;
        }
        if ($auth['username'] === $username && $auth['password'] === $password) {
            $token = bin2hex(openssl_random_pseudo_bytes(16));
            add_token($token);
            return_json(true, ['token' => $token]);
        }
    }
    // 認証失敗なら10秒待たせる
    sleep(10);
    return_json(false, "usernameまたはpasswordが違います");
}
// その他コマンド
elseif ($command === 'GET' || $command === 'ADD' || $command === 'CHANGE' || $command === 'DELETE') {
    // トークンのチェック
    if (!is_token_valid()) {
        sleep(10);
        return_json(false, 'トークンが正しくありません');
    }
    delete_old_token();

    // テーブル名を取得
    $table = filter_input(INPUT_GET, 'table');
    if (!$table) {
        return_json(false, 'テーブル名を指定してください');
    }
    if (!table_exists($table)) {
        return_json(false, 'テーブル名が正しくありません');
    }

    $where = filter_input(INPUT_POST, 'where');

    $wheres = [];
    $where_values = [];
    if ($where) {
        $where = json_decode($where, true);
        if ($where) {
            // 一つしか条件が無いときでも強制的に配列にする
            if (!is_vector($where)) {
                $where = [$where];
            }
            foreach ($where as $def) {
                if (!isset($def['column']) || !isset($def['cond']) || !isset($def['value'])) {
                    // whereの定義が全部あるかチェック。なければ次へ
                    continue;
                }
                if (!is_column_name_valid($def['column'])) {
                    // column名が正しくなければ次へ
                    continue;
                }
                $cond = $def['cond'];
                if (
                    $cond !== '=' && $cond !== '!=' && $cond !== '<>' &&
                    $cond !== '>' && $cond !== '<'  && $cond !== '>=' && $cond !== '<='
                ) {
                    // 基本的な比較演算子だけ利用可。それ以外なら次へ
                    continue;
                }
                $value = @strval($def['value']);

                $wheres[] = $def['column'] . $cond . ' ?';
                $where_values[] = $value;
            }

            $where = '';
            if ($wheres) {
                $where = " WHERE " . implode(' AND ', $wheres);
            }
        }
    }

    if ($command === 'GET') {
        $sql = "SELECT * FROM $table" . $where;
        exec_query($sql, $where_values);
    }

    if ($command === 'ADD') {
        $columns = [];
        $values  = [];
        decompose_input_data($columns, $values);
        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
        exec_query($sql, $values);
    }

    if ($command === 'CHANGE') {
        $columns = [];
        $values  = [];
        decompose_input_data($columns, $values);

        if (!$columns) {
            return_json(false, "アップデートするカラムがありません");
        }

        $fields = [];    
        foreach ($columns as $column) {
            $fields[] = $column . "=?";
        }
        $sql = "UPDATE $table SET " . implode(',', $fields) . $where;
        $values = array_merge($values, $where_values);
        exec_query($sql, $values);
    }

    if ($command === 'DELETE') {
        $sql = "DELETE FROM $table" . $where;
        exec_query($sql, $where_values);
    }
}
else {
    return_json(false, "コマンドエラー");
}
