<?php
/**
 * SQLite3データベースを操作する簡易JSONサーバーです。
 * 
 */

// 設定 -----------------------------------------------------

define('MIGRATE_FILENAME', 'migrate.json');    // マイグレーションファイルの名前
define('DB_FILENAME',      'db.sqlite3');      // SQLite3のデータベースファイルの名前
define('BASE_DIRNAME',     'jsonphp');         // 上記のファイルを格納するディレクトリ名
define('EXPIRATION',       30);                // トークンの有効期限（分）
// 次のdefineをコメントアウトすると自動的にホームディレクトリの下の jsonphp　を利用
// さくらのレンタルサーバー対策
// define('HOME',             '/var/www/dev/json/' . BASE_DIRNAME . '/');

// 認証に使うusersテーブルの初期データ
define('USERS_TABLE_DATA', [
    [
        "username" => 'admin',
        "password" => '$2y$10$MqTUmfUeTltU1MLti5u3eOzE6qpVWQ6IWzxBouXPYn.WBqf.WPn66', // PassWord
        "admin"    => 1, // 0=一般ユーザー、1=管理者。管理者に設定
        "email"    => 'admin@example.com'
    ],
    [
        "username" => 'guest',
        // guestユーザーの
        "password" => '$2y$10$xPu.qA.V2rBdimHI8z4w5ez/PUwGt6/Xz.nx.WgC6R71i6QGjadKq', // guest
        "admin"    => 0,
        "email"    => 'admin@example.com'
    ]
]);

// 設定おわり -----------------------------------------------






// usersテーブル定義（追加のみにしてください）
define('USERS_TABLE_DEF', [
    "id"         => "KEY",
    "username"   => "TEXT NOT NULL",
    "password"   => "TEXT NOT NULL",
    "email"      => "TEXT",
    "admin"      => "INTEGER NOT NULL DEFAULT 0",
    "created_at" => "TEXT",
    "updated_at" => "TEXT",
]);

/**
 * 初期設定
 */
ini_set('display_errors', 1);
header('Content-type: application/json; charset=utf-8');

// 現在の日時
$now = date('Y-m-d H:i:s');

if (!defined('HOME')) {
    // 定数HOMEが設定されていなければ、さくらのレンタルサーバー用のものを設定する
    $path_elements = explode('/', trim($_SERVER['DOCUMENT_ROOT'], '/'));
    $home = '/' . $path_elements[0] . '/' . $path_elements[1] . '/' . BASE_DIRNAME . '/';
    define('HOME', $home);
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
    if (!$result) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        $messageOrData .= ' (' . $caller['line'] . ')';
    }

    echo json_encode([
        'result' => $result,
        $result ? 'data' : 'message' => $messageOrData
    ], true);
    exit;
}

/**
 * is_column_name_valid
 * カラム名の妥当性チェック
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

// スキーマのバッファー
$_schema_buffer = [];

/**
 * get_schema
 * テーブルの構造を取得する
 * 
 * @param string $table テーブル名
 * @return array テーブル構造
 */
function get_schema($table) {
    global $pdo, $_schema_buffer;

    if (!table_exists($table)) {
        return [];
    }
    if (in_array($table, $_schema_buffer)) {
        return $_schema_buffer[$table];
    }
    $stmt = $pdo->query("PRAGMA table_info('{$table}');");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return_json(false, 'テーブルがありません（' . $table . '）');
    }

    $_schema_buffer[$table] = $rows;
    return $rows;
}

/**
 * has_column
 * テーブルがカラムを持っているか
 * 
 * @param string $table
 * @param string $column
 * @return bool
 */
function has_column($table, $column) {
    global $pdo;

    $cols = get_schema($table);
    foreach ($cols as $col) {
        if ($col['name'] === $column) {
            return true;
        }
    }
    return false;
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
 * @param array $columns 参照形式
 * @param array $values  参照形式
 */
function decompose_input_data(&$columns, &$values) {
    global $auth_user, $table;

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

    if (!is_admin()) {
        // 一般ユーザーなら
        $pos = array_search('user_id', $columns);
        if ($pos) {
            // もし user_id が指定されていたら、自分のIDに変更する
            $values[$pos] = $auth_user['id'];
        }
        elseif (has_column($table, 'user_id')) {
            // user_idカラムを持っていたら、強制的にセット
            $columns[] = 'user_id';
            $values[] = $auth_user['id'];
        }
    }
}

/**
 * exec_query
 * SQLを発行してJSONを出力します。
 * 
 * @param string $sql
 * @param array $binds
 */
function exec_query($sql, $binds) {
    global $pdo;

    $rows = [];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($sql);

        // $bindsが通常の配列なら、executeに直接渡す
        if (is_vector($binds)) {
            $stmt->execute($binds);
        }
        else {
            foreach ($binds as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
        }

        // 実行するSQLがSELECTだったら、値を返す
        if (substr($sql, 0, 6) === 'SELECT') {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (substr($sql, 0, 6) === 'INSERT') {
            $rows = [
                'id' => $pdo->lastInsertId()
            ];
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
 * @param int $user_id
 */
function add_token($token, $user_id) {
    global $pdo, $now;

    try {
        $stmt = $pdo->prepare('INSERT INTO jsonphp_sessions (token,stamp,user_id) VALUES (?,?,?)');
        $stmt->execute([$token, $now, $user_id]);
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }
}

/**
 * get_user_token
 * 
 * @param int $user_id
 * @return string
 */
function get_user_token($user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT token FROM jsonphp_sessions WHERE user_id=? AND stamp>=?');
        $stmt->execute([$user_id, date('Y-m-d H:i:s', time() - EXPIRATION * 60)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['token'];
        }
    }
    catch (PDOException $e) {
        // 握りつぶす
    }
    return false;
}

/**
 * get_user_id_by_token
 * tokenからuser_idを取得する。またtokenを更新する
 * 
 * @return array ユーザー情報
 */
function get_user_id_by_token() {
    global $pdo;

    $token = filter_input(INPUT_POST, 'token');
    if (!$token) {
        return false;
    }

    $user = false;
    try {
        $stmt = $pdo->prepare('SELECT U.id, U.admin, J.stamp '. 
            'FROM jsonphp_sessions AS J LEFT JOIN users AS U ON (J.user_id = U.id) '.
            'WHERE J.token=? ');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['stamp'] >= date('Y-m-d H:i:s', time() - EXPIRATION * 60)) {
            $user = $row;
        }
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }

    if ($user) {
        if (!update_token($token)) {
            return_json(false, "トークンの更新エラー");
        }
    }
    return $user;
}

/**
 * update_token
 * トークンの有効期限を延長する
 */
function update_token($token) {
    global $pdo, $now;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE jsonphp_sessions SET stamp=? WHERE token=?");
        $stmt->execute([$now, $token]);
    }
    catch(PDOException $e) {
        $pdo->rollBack();
        return false;
    }
    $pdo->commit();
    return true;
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

/**
 * make_where_condition
 * 複雑なwhere条件を作る
 * 
 * @param array $bind 参照形式
 * @param array $cond
 * @return string SQL
 * 
 */
function make_where_condition(array &$bind, array $cond) {
    global $auth_user, $table;
    if (!$cond) {
        $where = filter_input(INPUT_POST, 'where');
        if (!$where) {
            $cond = [];
        }
        else {
            $cond = json_decode($where, true);
            if (!is_vector($cond)) {
                $cond = [$cond];
            }
        }

        // 権限によって動作を変える
        if (!is_admin()) {
            // 一般ユーザーなら
            if ($table === 'users') {
                // usersテーブルは自分のデータのみ
                $cond[] = ['column' => 'id', 'cond' => '=', 'value' => $auth_user['id']];
            }
            elseif (has_column($table, 'user_id')) {
                // それ以外はuser_idというカラムを持っていたら、ログインユーザー所有のみに限定
                $cond[] = ['column' => 'user_id', 'cond' => '=', 'value' => $auth_user['id']];
            }
        }

        $rets = [];
        foreach ($cond as $c) {
            $rets[] = make_where_condition($bind, $c);
        }
        return $rets ? ' WHERE ' . implode(' AND ', $rets) : '';
    }
    else {
        if (isset($cond['OR'])) {
            if (count($cond['OR']) < 2) {
                throw new Error('ORは二つ以上条件が必要です');
            }
            $rets = [];
            foreach ($cond['OR'] as $c) {
                $rets[] = make_where_condition($bind, $c);
            }
            return '(' . implode(' OR ', $rets) . ')';
        }
        else {
            if (!isset($cond['column']) || !isset($cond['cond']) || !isset($cond['value'])) {
                throw new Error('Whereの条件が不正です');
            }
            if (!is_column_name_valid($cond['column'])) {
                throw new Error('Whereのカラム名が不正です');
            }
            $ss = strtoupper($cond['cond']);
            if (
                $ss !== '=' && $ss !== '!=' && $ss !== '<>' && $ss !== 'LIKE' &&
                $ss !== '>' && $ss !== '<'  && $ss !== '>=' && $ss !== '<='
            ) {
                throw new Error('Whereの比較演算子が不正です。');
            }
            $count = count($bind) + 1;
            $param = ':PHPBIND' . sprintf('%03d', $count);
            $bind[$param] =  @strval($cond['value']);

            return $cond['column'] . ' ' . $ss . ' ' . $param;
        }
    }
}

/**
 * create_table
 * 
 */
function create_table($table, $definitions) {
    global $pdo;

    if (table_exists($table)) {
        // テーブルが存在していたら、何もしない
        return;
    }

    if (is_string($definitions)) {
        $sql = $definitions;
    }
    else {
        $column_defs = [];
        foreach ($definitions as $name => $type) {
            // $typeは区切り文字だけは許さない
            if (preg_match('/[,;]/', $type)) {
                return_json(false, "テーブル：$table カラム：$name のカラムタイプに不正な文字があります");
            }
            if (!is_column_name_valid($name)) {
                // nameが正しくない（英数+アンダースコア1～100文字）
                return_json(false, "テーブル：$table カラム：$name のカラム名が正しくありません。");
            }
            if (strtoupper($type) === 'KEY') {
                $column_defs[] = $name . ' INTEGER PRIMARY KEY AUTOINCREMENT';
            }
            else {
                $column_defs[] = $name . ' ' . $type;
            }
        }
        $sql = 'CREATE TABLE ' . $table . ' (' . implode(',', $column_defs) . ')';
    }

    try {
        $pdo->exec($sql);
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }
}

/**
 * is_admin
 * 管理者かどうか
 */
function is_admin() {
    global $auth_user;

    return !!$auth_user['admin'];
}

/**
 * guest_exists
 * ゲストアカウントがあるか
 */
function guest_exists() {
    if (!defined('USERS_TABLE_DATA')) {
        return false;
    }
    foreach (USERS_TABLE_DATA as $row) {
        if ($row['username'] === 'guest') {
            return true;
        }
    }
    return false;
}

/**
 * get_the_guest
 * ゲストユーザーを取得
 */
function get_the_guest() {
    global $pdo;
    if (!guest_exists()) {
        return false;
    }
    $user = false;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username='guest'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $user = $row;
        }
    }
    catch(PDOException $e) {
        return_json(false, "SQLエラー：" . $e->getMessage());
    }
    return $user;
}










// ---------------------------------------------------------------------
// メイン
// ---------------------------------------------------------------------

// 認証済みユーザーのID
$auth_user = [];

// コマンドを取得
$command = strtoupper(filter_input(INPUT_GET, 'cmd'));

// PDOでデータベースへ接続＆設定
try {
    $pdo = new \PDO('sqlite:' . HOME . DB_FILENAME);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 外部キー制約を有効にする
    $pdo->exec('PRAGMA foreign_keys=true');
}
catch (PDOException $e) {
    return_json(false, "PDOエラー：" . $e->getMessage());
}

// ---------------------------------------------------------------------
// マイグレーション
// ---------------------------------------------------------------------
if ($command === 'MIGRATE') {

    // 管理テーブルの作成
    $sql = 'CREATE TABLE jsonphp_sessions (' .
        'token TEXT UNIQUE NOT NULL, '.
        'user_id INTEGER NOT NULL, '.
        'stamp TEXT NOT NULL, '.
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'.
    ')';
    create_table('jsonphp_sessions', $sql);

    // usersテーブルの作成
    if (defined('USERS_TABLE_DEF')) {
        create_table('users', USERS_TABLE_DEF);

        // 初期usersデータを追加する
        if (defined('USERS_TABLE_DATA')) {
            try {
                foreach (USERS_TABLE_DATA as $row) {
                    $placeholders = array_fill(0, count($row)+2, '?');
                    $fields = array_keys($row);
                    $fields[] = 'created_at';
                    $fields[] = 'updated_at';
                    $stmt = $pdo->prepare('INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')');

                    $values = array_values($row);
                    $values[] = $now;
                    $values[] = $now;
                    $stmt->execute($values);
                }
            }
            catch(PDOException $e) {
                return_json(false, "SQLエラー：" . $e->getMessage());
            }
        }
    }
    else {
        return_json(false, 'usersテーブルの定義がありません');
    }

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

    // マイグレーションファイルからの追加
    foreach ($table_defs as $table => $columns) {
        create_table($table, $columns);
    }

    return_json(true, []);
}
// ---------------------------------------------------------------------
// 認証
// ---------------------------------------------------------------------
elseif ($command === 'AUTH') {
    $username = filter_input(INPUT_POST, 'username');
    $password = filter_input(INPUT_POST, 'password');
    
    if (!$username || !$password || $username === 'guest') {
        // guestはパスワード認証させない（トークンを作らせない）
        return_json(false, 'usernameまたはpasswordが指定されていません');
    }

    $stmt = $pdo->prepare('SELECT id,password FROM users WHERE username=?');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($password, $row['password'])) {
        $token = get_user_token($row['id']);
        if ($token) {
            update_token($token);
        }
        else {
            $token = bin2hex(openssl_random_pseudo_bytes(16));
            add_token($token, $row['id']);
        }
        return_json(true, ['token' => $token]);
    }
    // 認証失敗なら5秒待たせる
    sleep(5);
    return_json(false, "usernameまたはpasswordが違います");
}
// ---------------------------------------------------------------------
// 取得・追加・更新・削除
// ---------------------------------------------------------------------
elseif ($command === 'GET' || $command === 'ADD' || $command === 'UPDATE' || $command === 'DELETE') {
    // ---------------------------------------------------------------------
    // 共通部分
    // ---------------------------------------------------------------------

    // テーブル名を取得
    $table = filter_input(INPUT_GET, 'table');
    if (!$table) {
        return_json(false, 'テーブル名を指定してください');
    }
    if (!table_exists($table)) {
        return_json(false, 'テーブル名が正しくありません');
    }

    // トークンのチェック
    $auth_user = get_user_id_by_token();
    if (!$auth_user) {
        if (!has_column($table, 'user_id')) {
            $auth_user = get_the_guest();
        }
        if (!$auth_user) {
            return_json(false, 'アクセスできません');
        }
    }
    delete_old_token();

    // ---------------------------------------------------------------------
    // whereがある場合は設定される
    // ---------------------------------------------------------------------
    $bind = [];
    $where = make_where_condition($bind, []);

    // ---------------------------------------------------------------------
    // orderがある場合は設定される
    // ---------------------------------------------------------------------
    $orderBy = [];
    $order = filter_input(INPUT_POST, 'order');
    if ($order) {
        $orderBy = json_decode($order, true);
    }
    $orderBy = implode(',', $orderBy);
    $orderBy = $orderBy ? ' ORDER BY '.$orderBy : '';

    // ---------------------------------------------------------------------
    // fieldがある場合は設定される
    // ---------------------------------------------------------------------
    $fields = [];
    $field = filter_input(INPUT_POST, 'field');
    if ($field) {
        $fields = json_decode($field, true);
    }
    $fields = implode(',', $fields);
    $fields = $fields ? $fields : '*';





    // ---------------------------------------------------------------------
    // GET
    // ---------------------------------------------------------------------
    if ($command === 'GET') {
        $sql = "SELECT " . $fields . " FROM $table" . $where . $orderBy;
        exec_query($sql, $bind);
    }

    // ---------------------------------------------------------------------
    // ADD
    // ---------------------------------------------------------------------
    if ($command === 'ADD') {
        if (!is_admin()) {
            if ($table === 'users') {
                return_json(false, '一般ユーザーはユーザーを追加できません');
            }
        }

        $columns = [];
        $values  = [];
        decompose_input_data($columns, $values);

        if (!in_array('created_at', $columns) && has_column($table, 'created_at')) {
            $columns[] = 'created_at';
            $values[] = $now;
        }
        if (!in_array('updated_at', $columns) && has_column($table, 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = $now;
        }
        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
        exec_query($sql, $values);
    }

    // ---------------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------------
    if ($command === 'UPDATE') {
        $columns = [];
        $values  = [];
        decompose_input_data($columns, $values);

        if (!$columns) {
            return_json(false, "アップデートするカラムがありません");
        }

        if (!in_array('updated_at', $columns) && has_column($table, 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = $now;
        }

        $fields = []; $params = [];
        foreach ($columns as $i => $column) {
            $param = ":PHPUPDATE" . sprintf('%03d', $i+1);
            $fields[] = $column . '=' . $param;
            $params[$param] = $values[$i];
        }
        $sql = "UPDATE $table SET " . implode(',', $fields) . $where;
        exec_query($sql, array_merge($params,$bind));
    }

    // ---------------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------------
    if ($command === 'DELETE') {
        $sql = "DELETE FROM $table" . $where;
        exec_query($sql, $bind);
    }
}
// ---------------------------------------------------------------------
// パスワードのハッシュを返す
// ---------------------------------------------------------------------
elseif ($command === 'HASH') {
    $password = filter_input(INPUT_GET, 'password');
    if (!$password) {
        return_json(false, 'passwordパラメータを指定してください');
    }
    return_json(true, ['hash' => password_hash($password, PASSWORD_DEFAULT )]);
}
// ---------------------------------------------------------------------
// コマンドエラー
// ---------------------------------------------------------------------
else {
    return_json(false, "コマンドエラー");
}
