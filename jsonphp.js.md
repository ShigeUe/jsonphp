# jsonphp.js

- ブラウザのセキュリティ上、同一ドメインから以外のアクセス出来ないようになっています。
- [Fetch API](https://developer.mozilla.org/ja/docs/Web/API/Fetch_API) などを利用していますので、モダンブラウザ以外（IE11）では動作しません。
- チェックはChromeとFirefoxでのみ行っています。

&nbsp;

## import

```
// 同一階層のjsonphp.jsを読み込みます。
import { JsonPHP } from './jsonphp.js';
```

&nbsp;

## リファレンス

### メソッドの失敗

ほとんどのメソッドが失敗すると例外を送出します。  
エラーメッセージが送られているので、 `catch` してください。

もしくは `.then` と `.catch` を使って、エラーを補足してください。  
詳しくは `sample.html` をご覧ください。

### 設定

URLを設定します。

```
// json.phpにアクセスするURLを設定し、インスタンスを取得します。
JsonPHP.init({ url: 'https://example.com/json.php' });
```

### Classメソッド

#### マイグレーション

```
await JsonPHP.migrate();
```

#### パスワードハッシュ

引数にパスワードを渡すと、ハッシュ化されたものを返します。

```
hash = await JsonPHP.hash(password);
```

#### トークンのセット

すでにログインしている場合、 `token` をJsonPHPにセットしてください。  
このメソッドは `JsonPHP` のインスタンスを返しますので、メソッドチェーン出来ます。

```
jsonphp = JsonPHP.setToken(token);
```

#### トークンを取得する

ログインした後に、ローカルストレージなどに `token` を保存したい場合などに使います。

```
token = JsonPHP.getToken();
```

#### ログイン

`Promise` を返します。 `Promise` が解決すると `JsonPHP` のインスタンスを返します。

```
jsonphp = await JsonPHP.auth(username, password);
```

### インスタンスメソッド

`jsonphp` にインスタンスが入っているとします。

#### tableメソッド

インスタンスで利用するテーブルを設定します。インスタンスを返しますので、 `where` 、`orWhere`, `order` 、 `get` 、 `add` 、 `update` 、 `delete` 、はメソッドチェーンで指定します。

```
jsonphp.table('books'); // JsonPHPのインスタンスを返します。
```

#### where・orWhereメソッド

`where` メソッドの引数は検索用のパラメータです。`[カラム,検索条件,値]` という配列 または 3つの引数で指定します。  
`where` メソッドを複数回呼び出すと、 `AND` 条件になります。

例：

```
data = await jsonphp.table('books').where('id', '=', 10).get();
```

`orWhere` メソッドは二つ以上指定すると `OR` 条件になります。 `orWhere` メソッドで指定した条件だけが `OR` になり、その他は `AND` で結合されます。

例：

```
data = await jsonphp
    .table('books')
    .where('user_id','=',1)
    .orWhere('price','<',1000)
    .orWhere('price','>',90000)
    .get();

// user_id = 1 AND (price < 1000 OR price > 90000)
```

`orWhere` メソッドが一つだけだと `AND` 条件と変わりません。

例：

```
data = await JsonPHP
    .table('books')
    .where('user_id','=',1)
    .orWhere('price','<',1000)
    .get();

// user_id = 1 AND (price < 1000)
```

#### 並べ替え（order by）

`order` メソッドで並べ替えを指定できます。  
複数引数か、配列で条件を与えます。  
このメソッドもインスタンスを返します。

例：

```
data = await jsonphp
    .table('books')
    .where('price','>',1000)
    .order('user_id', 'price DESC')
    // or .order(['user_id', 'price DESC'])
    .get();

// ORDER BY user_id, price DESC
```

#### 取得フィールドの限定

`field` メソッドで取得するフィールドを限定できます。
複数引数か、配列で条件を与えます。  
このメソッドもインスタンスを返します。

例：

```
data = await jsonphp
    .table('books')
    .field('id', 'name', 'price')
    .get();

// SELECT id,name,price FROM books;
```

#### データの取得

テーブルに `user_id` があれば、ログインしているユーザーの `id` が `where` に自動的に設定されます。

```
// 以下の例では全件取得
data = await jsonphp.table('books').get();
```

#### データの追加

`add` メソッドの引数はオブジェクトでデータを与えます。  
 テーブルに `created_at` と `updated_at` のカラムがあれば自動的に設定されます。  
 テーブルに `user_id` があれば、ログインしているユーザーの `id` が自動的に設定されます。  
&nbsp;  
このメソッドは `Promise` を返します。 `Promise` が解決されると、挿入されたレコードの `id` を返します。

```
{ id } = await JsonPHP.table('books').add({user_id: 1, name: 'PHPマニュアル', price: 2900});
```

#### データの更新

`update` メソッドの第一引数は更新するデータを与えます。テーブルに `updated_at` カラムがあれば自動的に設定されます。  
テーブルに `user_id` があれば、ログインしているユーザーの `id` が設定されます。  
&nbsp;  
第二引数は `get` メソッドと同じ検索用のパラメータです。  
テーブルに `user_id` があれば、 検索用のパラメータにログインしているユーザーの `id` が自動的に設定されます。  
&nbsp;  
このメソッドは `Promise` を返します。 `Promise` が解決されると空の配列を返します。


```
await JsonPHP.table('books').where('id', '=', 22).update({price: 3900});
```

#### データの削除

`delete` メソッドの引数は `get` メソッドと同じ検索用のパラメータです。  
引数を与えないと全件削除します。ご注意ください。

テーブルに `user_id` があれば、ログインしているユーザーの `id` が `where` に自動的に設定されます。  
&nbsp;  
このメソッドは `Promise` を返します。 `Promise` が解決されると空の配列を返します。

```
await JsonPHP.table('books').where('id', '=', 22).delete();
```
