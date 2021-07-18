export default class JsonPHP {
  // -------------------------------------------------------------------------
  // スタティック用
  // -------------------------------------------------------------------------

  static token = '';

  static url = '';

  static init(options) {
    if (typeof options !== 'object' || !options.url) {
      throw new Error('設定が正しくありません');
    }
    JsonPHP.url = options.url;
  }

  static new() {
    return new JsonPHP();
  }

  static setToken(token) {
    JsonPHP.token = token;
    return new JsonPHP();
  }

  static getToken() {
    return JsonPHP.token;
  }

  static async migrate() {
    const ret = await JsonPHP.$fetch(`${JsonPHP.url}?cmd=migrate`, {
      method: 'GET',
    });
    if (ret.result) {
      return true;
    }
    throw ret.message;
  }

  static async auth(username, password) {
    const params = {};
    params.body = JsonPHP.makeFd({ username, password });
    params.method = 'POST';

    const ret = await JsonPHP.$fetch(`${JsonPHP.url}?cmd=auth`, params);

    if (ret.result) {
      return JsonPHP.setToken(ret.data.token);
    }
    throw ret.message;
  }

  static async hash(password) {
    const encodedPassword = encodeURIComponent(password);
    const ret = await JsonPHP.$fetch(`${JsonPHP.url}?cmd=hash&password=${encodedPassword}`, {
      method: 'GET',
    });

    if (ret.result) {
      return ret.data.hash;
    }
    throw ret.message;
  }

  static makeFd(obj) {
    const data = new FormData();
    Object.keys(obj).forEach((el) => {
      data.append(el, obj[el]);
    });
    return data;
  }

  static async $fetch(url, param) {
    const res = await fetch(url, param);
    if (res.ok) {
      return res.json();
    }

    return {
      result: false,
      message: `接続できませんでした（${res.status}）`,
    };
  }

  // -------------------------------------------------------------------------
  // インスタンス用
  // -------------------------------------------------------------------------

  $table = null;

  $field = [];

  $order = [];

  $where = [];

  $orWhere = [];

  clearInstanceVars() {
    this.$table = null;
    this.$field = [];
    this.$order = [];
    this.$where = [];
    this.$orWhere = [];
  }

  table(table) {
    this.clearInstanceVars();
    this.$table = table;
    return this;
  }

  arrayToWhere(arr) {
    const ret = [];
    if (typeof arr === 'object' && arr.constructor.name !== 'Array') {
      return ret;
    }
    if (typeof arr[0] === 'object' && arr[0].constructor.name === 'Array') {
      arr.forEach((a) => {
        ret.push(this.arrayToWhere(a));
      });
    } else {
      return {
        column: arr[0],
        cond: arr[1],
        value: arr[2],
      };
    }
    return ret;
  }

  where(...cond) {
    if (typeof cond[0] === 'object' && cond[0].constructor.name === 'Array') {
      this.$where.push(cond[0]);
    } else {
      this.$where.push([cond[0], cond[1], cond[2]]);
    }
    return this;
  }

  orWhere(...cond) {
    if (typeof cond[0] === 'object' && cond[0].constructor.name === 'Array') {
      this.$orWhere.push(cond[0]);
    } else {
      this.$orWhere.push([cond[0], cond[1], cond[2]]);
    }
    return this;
  }

  order(...orders) {
    if (typeof orders[0] === 'object' && orders[0].constructor.name === 'Array') {
      this.$order = this.$order.concat(orders[0]);
    } else {
      this.$order = this.$order.concat(orders);
    }
    return this;
  }

  field(...fields) {
    if (typeof fields[0] === 'object' && fields[0].constructor.name === 'Array') {
      this.$field = this.$field.concat(fields[0]);
    } else {
      this.$field = this.$order.concat(fields);
    }
    return this;
  }

  addFieldByToBody(body) {
    const ret = body;
    if (this.$field.length) {
      ret.field = JSON.stringify(this.$field);
    }
  }

  addOrderByToBody(body) {
    const ret = body;
    if (this.$order.length) {
      ret.order = JSON.stringify(this.$order);
    }
  }

  addWhereToBody(body) {
    const ret = body;

    let where = [];
    if (this.$orWhere.length) {
      if (this.$orWhere.length === 1) {
        this.$where = this.$where.concat(this.$orWhere);
      } else if (this.$orWhere.length > 1) {
        where.push({ OR: this.arrayToWhere(this.$orWhere) });
      }
    }
    if (this.$where.length) {
      where = where.concat(this.arrayToWhere(this.$where));
    }
    if (where.length) {
      ret.where = JSON.stringify(where);
    }
    this.$where = [];
    this.$orWhere = [];
  }

  async fetch(mode, body) {
    const inputBody = body;
    if (JsonPHP.token) {
      inputBody.token = JsonPHP.token;
    }

    const params = {};
    params.method = 'POST';
    params.body = JsonPHP.makeFd(inputBody);

    const ret = await JsonPHP.$fetch(`${JsonPHP.url}?cmd=${mode}&table=${this.$table}`, params);

    this.clearInstanceVars();

    if (ret.result) {
      return ret.data;
    }
    throw ret.message;
  }

  async get() {
    const body = {};
    this.addWhereToBody(body);
    this.addOrderByToBody(body);
    this.addFieldByToBody(body);
    return this.fetch('get', body);
  }

  async add(data) {
    const body = {};
    body.data = JSON.stringify(data);
    return this.fetch('add', body);
  }

  async update(data) {
    const body = {};
    body.data = JSON.stringify(data);
    this.addWhereToBody(body);
    return this.fetch('update', body);
  }

  async delete() {
    const body = {};
    this.addWhereToBody(body);
    return this.fetch('delete', body);
  }
}
