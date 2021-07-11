# jsonphp.js

## 利用方法

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

#### データの取得

`get` メソッドの引数は検索用のパラメータです。`[カラム,検索条件,値]` という配列です。  
`[[カラム,検索条件,値],[カラム,検索条件,値]]` という感じで指定すると `AND` 検索になります。

```
data = await JsonPHP.table('books').get(['id', '=', 10]);
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
await JsonPHP.table('books').change({price: 3900}, ['id', '=', 22]);
```

#### データの削除

`delete` メソッドの引数は `get` メソッドと同じ検索用のパラメータです。  
引数を与えないと全件削除します。ご注意ください。

```
await JsonPHP.table('books').delete(['id', '=', 22]);
```
