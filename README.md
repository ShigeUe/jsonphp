# json php

## 概要

ポートフォリオサイトを作る時などに、簡易なAPIサーバがあると便利なのではないかと思い、作成しました。  
安価な「さくらインターネット」で利用することを前提にしてますが、その他の環境でも動くはずです。

## インストール

ApacheなどのWebサーバを利用するときは、Webサーバのドキュメントルート以下のどこかに設置します。

ローカルで開発用に稼働させたい場合は、PHPのビルトインサーバを利用します。  
例えば、3000番のポートで稼働させたい場合、

```
php -S 127.0.0.1:3000 json.php
```

とすることで利用できます。

## 設定

### アプリケーションの設定

`json.php` ファイルの先頭部分に `define` がありますが、それを変更します。

- `HOME`
   - `HOME` をコメントアウトすると、ホームディレクトリ直下の `jsonphp` ディレクトリを使うようになります。  
さくらのレンタルサーバーの場合は、 `HOME` をコメントアウトすると簡単です。  
`jsonphp` ディレクトリはアプリケーションから書き込めるように権限を設定してください。  
またこのディレクトリは **Webからアクセスできない安全なところ** を指定してください。
- `EXPIRATION`
    - トークンの有効期限です。この有効期限が切れたトークンは次のアクセス時に削除されます。
- `USERS_TABLE_DATA`
    - 認証用の `users` テーブルの初期データです。マイグレーション時に使います。
- `その他の定数`
    - ファイル名やディレクトリ名です。必要が無ければ書き換えないことをお勧めします。

### データベースの設定

マイグレーションファイルを使ってテーブルを作成します。  
`HOME` ディレクトリの中に `migrate.json` ファイルを作成します。  
マイグレーションは指定のテーブルが存在する場合は、スキップされます。  
再作成したい場合は、 `DROP TABLE` してください。

例：

```
{
    "books": {
        "id": "KEY",
        "user_id": "INTEGER",
        "name": "TEXT",
        "price": "INTEGER",
        "created_at": "TEXT",
        "updated_at": "TEXT",
        "FOREIGN": "KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
    }
}
```

ルートのオブジェクトのキーがテーブル名、値がテーブル定義です。  
テーブル定義の中のキーがカラム名、値がカラムタイプです。  
カラムタイプで `KEY` は `INTEGER PRIMARY KEY AUTOINCREMENT` に置き換えられます。  
最後の行でちょっと強引ですが、リレーションの設定をしています。  
複雑なテーブルになる場合は、SQLを直接書く設定をご利用ください。

&nbsp;

ルートのオブジェクトの値を文字列にすると、直接SQLを書くことが出来ます。

例：

```
{
    "user_meta": "CREATE TABLE user_meta (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, `key` TEXT NOT NULL, `value` TEXT, created_at TEXT, updated_at TEXT)"
}
```

&nbsp;

マイグレーション時に `users` テーブルを作り、初期のユーザーを登録します。  
最初に登録したいユーザーがいたら、 `USERS_TABLE_DATA` に設定してください。

## リポジトリのファイルの説明

- `json.php`
    - 本体。この中で設置するのはこれだけです。
- `jsonphp.js`
    - Javascriptから簡単に使うことを目指したモジュールです。
- `jsonphp.js.md`
    - `jsonphp.js` の [使い方](./jsonphp.js.md) です。
- `migrate.json`
    - マイグレーションファイルのサンプル
- `sample.html`
    - Javascriptからのアクセスサンプル。 `jsonphp.js` を使っています。

## 認証

- `users` テーブルで認証します。
- パスワードはハッシュしてください。ハッシュの作成は「利用方法」をご覧ください。



## 利用方法

### 前提条件

http://example.com/json.php に設置されていると仮定します。

### マイグレーションの実行 （一番最初に実行する）

- URL: http://example.com/json.php?cmd=migrate
- METHOD: GET
- 返されるJSON例

```
{
    "result": true,
    "data": []
}
```

### 認証

- http://example.com/json.php?cmd=auth
- METHOD: POST
- DATA:
    - `username` （必須）
    - `password` （必須）
- 返されるJSON例

```
{
    "result": true,
    "data": {
        "token": "3fbc0ba6d721ef52154a307a337cdde3"
    }
}
```

### データ追加

- http://example.com/json.php?cmd=add&table=books
- METHOD: POST
- DATA:
    - `token` （必須）
    - `data` （必須）
        - 例： `{"name":"日本の歴史","price":"1780","created_at":"2020-01-02 03:04:50","updated_at":"2020-01-02 03:04:50"}`
- 返されるJSON例

```
{
    "result": true,
    "data": []
}
```

### データ更新

- http://example.com/json.php?cmd=change&table=books
- METHOD: POST
- DATA:
    - `token` （必須）
    - `where` （オプション）配列で複数指定するとAND検索
        - 例： `{"column":"name","cond":"=","value":"日本の歴史"}`
    - `data` （必須）
        - 例： `{"price":"2250","updated_at":"2020-01-03 11:11:22"}`
- 返されるJSON例

```
{
    "result": true,
    "data": []
}
```

### データの削除

- http://example.com/json.php?cmd=delete&table=books
- METHOD: POST
- DATA:
    - `token` （必須）
    - `where` （オプション）
        - 例： `{"column":"id","cond":"=","value":12}`
- 返されるJSON例

```
{
    "result": true,
    "data": []
}
```

### データの取得

- http://example.com/json.php?cmd=get&table=books
- METHOD: POST
- DATA:
    - `token` （必須）
    - `where` （オプション）
        - 例： `{"column":"price","cond":"<","value":3000}`
- 返されるJSON例

```
{
    "result": true,
    "data": [
        {
            "id": "1",
            "name": "日本の歴史",
            "price": "2250",
            "created_at": "2020-01-02 03:04:50",
            "updated_at": "2020-01-03 11:11:22"
        }
    ]
}
```

### where について

- `column` ：カラム名
- `cond` ：比較演算子
    - `=` `!=` `<>` `>` `<` `>=` `<=` `LIKE` の8種類
- `value` ：値
- `OR` を利用することも可能

例：

```
[
    {
        OR: [
            { column: price, cond: "<", value: 1000 },
            { column: price, cond: ">", value: 90000 }
        ]
    },
    { column: user_id, cond:"=", value: 1 }
]
```

### ハッシュの作成

- http://example.com/json.php?cmd=hash&password=ハッシュしたい文字
- METHOD: GET
- 返されるJSON例

```
{
    "result": true,
    "data": [
        {
            "hash": "$2y$10$OEavWEQnX13A7Gg0WPy6ruWEZhK3bgoOaOo24U7g9RDoa1Ki7L6Ne",
        }
    ]
}
```
