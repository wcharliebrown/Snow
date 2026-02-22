<?php
class ProductsListReport {
    public function name() {
        return 'products_list';
    }
    public function description() {
        return 'Products — custom table list view';
    }
    public function sql_table() {
        return 'products';
    }
    public function sql_fields() {
        return 'id,
    product_name,
    price,
    description';
    }
    public function sql_where() {
        return NULL;
    }
    public function sql_order() {
        return 'id DESC';
    }
    public function rows_per_page() {
        return 50;
    }
    public function output_format() {
        return 'html';
    }
    public function html_header() {
        return '<table class="table table-striped table-hover">
<thead class="table-dark">
<tr><th>ID</th><th>Product Name</th><th>Price</th><th>Description</th><th>Actions</th></tr>
</thead><tbody>';
    }
    public function html_row_template() {
        return '<tr><td>{{id}}</td><td>{{product_name}}</td><td>{{price}}</td><td>{{description}}</td><td><a href="/admin/data/products?action=edit&amp;id={{id}}" class="btn btn-primary btn-sm">Edit</a></td></tr>';
    }
    public function html_footer() {
        return '</tbody></table>';
    }
}
