class JsonPHP {
    // -------------------------------------------------------------------------
    // スタティック用
    // -------------------------------------------------------------------------

    static token;
    static url;

    static init(options) {
        if (typeof options === 'object') {
            if (options.url) {
                JsonPHP.url = options.url;
            }
        }
        return JsonPHP;
    }

    static make_fd(obj) {
        const data = new FormData;
        Object.keys(obj).forEach(el => {
            data.append(el, obj[el]);
        });
        return data;
    }

    static setToken(token) {
        JsonPHP.token = token;
        return JsonPHP;
    }

    static table(table) {
        return new JsonPHP(table);
    }

    static async migrate() {
        const res = await fetch(JsonPHP.url + "?cmd=migrate", {
            method: "GET"
        });
        const ret = await res.json();
        if (ret.result) {
            return true;
        }
        else {
            throw ret.message;
        }
    }

    static async auth(username, password) {
        const params = {};
        params.body = JsonPHP.make_fd({ username, password });
        params.method = "POST";

        const res = await fetch(JsonPHP.url + "?cmd=auth", params);
        const ret = await res.json();

        if (ret.result) {
            JsonPHP.token = ret.data.token;
            return ret.data.token;
        }
        else {
            throw ret.message;
        }
    }

    static async hash(password) {
        const res = await fetch(JsonPHP.url + "?cmd=hash&password=" + encodeURIComponent(password), {
            method: "GET"
        });
        const ret = await res.json();

        if (ret.result) {
            return ret.data.hash;
        }
        else {
            throw ret.message;
        }
    }

    // -------------------------------------------------------------------------
    // インスタンス用
    // -------------------------------------------------------------------------

    _table = null;
    _order = [];
    _where = [];
    _orWhere = [];

    constructor(table) {
        if (!JsonPHP.token) {
            throw 'ログインするかtokenをセットしてください';
        }
        this._table = table;
    }

    arrayToWhere(arr) {
        const ret = [];
        if (typeof arr === 'object' && arr.constructor.name !== 'Array') {
            return ret;
        }
        if (typeof arr[0] === 'object' && arr[0].constructor.name === 'Array') {
            arr.forEach(a => {
                ret.push(this.arrayToWhere(a));
            });
        }
        else {
            return {
                column: arr[0],
                cond:   arr[1],
                value:  arr[2]
            };
        }
        return ret;
    }

    where(...cond) {
        if (typeof cond[0] === 'object' && cond[0].constructor.name === 'Array') {
            this._where.push(cond[0]);
        }
        else {
            this._where.push([cond[0],cond[1],cond[2]]);
        }
        return this;
    }

    orWhere(...cond) {
        if (typeof cond[0] === 'object' && cond[0].constructor.name === 'Array') {
            this._orWhere.push(cond[0]);
        }
        else {
            this._orWhere.push([cond[0],cond[1],cond[2]]);
        }
        return this;
    }

    order(...orders) {
        if (typeof orders[0] === 'object' && orders[0].constructor.name === 'Array') {
            this._order.push(orders[0]);
        }
        else {
            this._order = this._order.concat(orders);
        }
        return this;
    }

    addOrderByToBody(body) {
        if (this._order.length) {
            body.order = JSON.stringify(this._order);
        }
    }

    addWhereToBody(body) {
        let where = [];
        if (this._orWhere.length) {
            if (this._orWhere.length === 1) {
                this._where = this._where.concat(this._orWhere);
            }
            else if (this._orWhere.length > 1) {
                where.push({ OR: this.arrayToWhere(this._orWhere) });
            }
        }
        if (this._where.length) {
            where = where.concat(this.arrayToWhere(this._where));
        }
        if (where.length) {
            body.where = JSON.stringify(where);
        }
        this._where = [];
        this._orWhere = [];
    }

    async fetch(mode, body) {

        body.token = JsonPHP.token;

        const params = {};
        params.method = 'POST';
        params.body = JsonPHP.make_fd(body);

        const res = await fetch(JsonPHP.url + "?cmd=" + mode + "&table=" + this._table, params);
        const ret = await res.json();

        if (ret.result) {
            return ret.data;
        }
        else {
            throw ret.message;
        }
    }

    async get() {
        const body = {};
        this.addWhereToBody(body);
        this.addOrderByToBody(body);
        return this.fetch("get", body);
    }

    async add(data) {
        const body = {};
        body.data = JSON.stringify(data);
        return this.fetch("add", body);
    }

    async change(data) {
        const body = {};
        body.data = JSON.stringify(data);
        this.addWhereToBody(body);
        return this.fetch("change", body);
    }

    async delete() {
        const body = {};
        this.addWhereToBody(body);
        return this.fetch("delete", body);
    }
}

export { JsonPHP };
