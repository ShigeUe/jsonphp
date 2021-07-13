# jsonphp.js

## 利用方法

- ブラウザのセキュリティ上、同一ドメインから以外のアクセス出来ないようになっています。
- [Fetch API](https://developer.mozilla.org/ja/docs/Web/API/Fetch_API) を利用していますので、モダンブラウザ以外（IE11）では動作しません。
- チェックはChromeとFirefoxでのみ行っています。

&nbsp;

### import

```
// 同一階層のjsonphp.jsを読み込みます。
import { JsonPHP } from './jsonphp.js';
```

### 設定

```
// json.phpにアクセスするURLを設定します。
JsonPHP.init({ url: 'https://example.com/json.php' });
```

&nbsp;

### リファレンス

#### メソッドの失敗

ほとんどのメソッドが失敗すると例外を送出します。  
エラーメッセージが送られているので、 `catch` してください。  
詳しくは `sample.html` をご覧ください。

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
このメソッドは `Promise` を返しません。

```
JsonPHP.setToken(token);
```

#### ログイン

```
// このメソッドはトークンを返しますが、JsonPHP.tokenでも取り出せます。
token = await JsonPHP.auth(username, password);
```

#### where・orWhereメソッド

`where` メソッドの引数は検索用のパラメータです。`[カラム,検索条件,値]` という配列 または 3つの引数で指定します。  
`where` メソッドを複数回呼び出すと、 `AND` 条件になります。

例：

```
data = await JsonPHP.table('books').where('id', '=', 10).get();
```

`orWhere` メソッドは二つ以上指定すると `OR` 条件になります。 `orWhere` メソッドで指定した条件だけが `OR` になり、その他は `AND` で結合されます。

例：

```
data = await JsonPHP
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

#### データの取得

```
// 以下の例では全件取得
data = await JsonPHP.table('books').get();
```

#### データの追加

`add` メソッドの引数はオブジェクトでデータを与えます。  
 テーブルに `created_at` と `updated_at` のカラムがあれば自動的に設定されます。

```
await JsonPHP.table('books').add({user_id: 1, name: 'PHPマニュアル', price: 2900});
```

#### データの更新

`change` メソッドの第一引数は更新するデータを与えます。テーブルに `updated_at` カラムがあれば自動的に設定されます。  
第二引数は `get` メソッドと同じ検索用のパラメータです。

```
await JsonPHP.table('books').where('id', '=', 22).change({price: 3900});
```

#### データの削除

`delete` メソッドの引数は `get` メソッドと同じ検索用のパラメータです。  
引数を与えないと全件削除します。ご注意ください。

```
await JsonPHP.table('books').where('id', '=', 22).delete();
```
