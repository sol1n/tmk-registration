<template>
    <div class="file-table">

    <div class="row">
        <div class="col-md-10">
            <input placeholder="search" type="text" class="form-control" v-model="search">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" @click="doSearch()">Search</button>
        </div>
    </div>
    <!--<div class="row">-->
        <!--<div class="col-md-8">-->
            <!--<label><input type="radio" name="orderBy" value="Name"/>Name</label>-->
            <!--<label><input type="radio" name="orderBy" value="Size"/>Size</label>-->
            <!--<label><input type="radio" name="orderBy" value=""/>Created At</label>-->
            <!--<label><input type="radio" name="orderBy" />Owner</label>-->
        <!--</div>-->
    <!--</div>-->
    <br>
    <table class="table">
        <thead>
        <tr>
            <th>#</th>
            <th></th>
            <th><span class="sortable-header" @click="sort('name')">Name <i class="fa" :class="getOrderClass('name')"></i></span></th>
            <th><span class="sortable-header" @click="sort('length')">Size <i class="fa" :class="getOrderClass('length')"></i></span></th>
            <th><span class="sortable-header" @click="sort('createdAt.date')">Created At <i class="fa" :class="getOrderClass('createdAt.date')"></i></span></th>
            <th><span class="sortable-header" @click="sort('ownerName')">Owner <i class="fa" :class="getOrderClass('ownerName')"></i></span></th>
            <th>Permissions</th>
            <th class="text-center">Shared</th>
            <th class="text-right">Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="treeItem in filteredFiles">
            <td>
                <a :href="treeItem.link">{{ treeItem.id }}</a>
            </td>
            <td class="text-center">
                <i v-if="treeItem.fileType == 'directory'" class="fa fa-folder-o" title="directory" aria-hidden="true"></i>
                <i v-else class="fa fa-file-o" title="file" aria-hidden="true"></i>
            </td>
            <td>{{ treeItem.name ? treeItem.name : treeItem.id }}</td>
            <td>{{ getSize(treeItem) }}</td>
            <td>{{ formatDate(treeItem.createdAt.date) }}</td>
            <td>{{ treeItem.ownerName }}</td>
            <td class="text-center">{{ getRights(treeItem) }}</td>
            <td class="text-center">
                <i v-if="treeItem.shareStatus == 'shared'" class="fa fa-check" title="shared" aria-hidden="true"></i>
                <i v-else class="fa fa-minus" title="not shared" aria-hidden="true"></i>
            </td>
            <td class="text-right">Actions</td>
        </tr>
        </tbody>
    </table>
    </div>
</template>

<script>

    var moment = require('moment');
    var axios = require('axios');


    export default {
        name: 'file-table',
        props: ['fileList', 'initSortField', 'initSortOrder'],
        data() {
            return {
                search: '',
                files: [],
                sortFieldName: '',
                sortFieldOrder: ''
            }
        },
        methods: {
            getSize(item) {
                return item.length;
            },
            getRights(item) {
                var result = '';
                var tmp = [];
                if (item.rights['total']) {
                    Object.keys(item.rights['total']).forEach(function (item, index, arr) {
                        tmp.push(item.split('.')[0]);
                    });
                }
                result = tmp.join('|');
                return result;
            },
            formatDate(value) {
                return moment(String(value)).format('DD.MM.YYYY hh:mm');
            },
            sort(fieldName) {
                if (fieldName == this.sortFieldName) {
                    this.sortFieldOrder = (this.sortFieldOrder == 'asc' ? 'desc' : 'asc');
                }
                else {
                    this.sortFieldName = fieldName;
                    this.sortFieldOrder = 'asc';
                }
                axios.get('/files/set-order', {
                    params: {
                        field: this.sortFieldName,
                        order: this.sortFieldOrder
                    }
                })
                    .then(function (response) {
                        console.log(response);
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },
            getOrderClass(fieldName){
                var result = 'fa-sort';
                if (this.sortFieldName == fieldName){
                    result = 'fa-sort-' + this.sortFieldOrder;
                }
                return result;
            },
            doSearch() {
                if (this.search) {
                    var link = '/files/search?query=' + this.search;
                    window.location.href = link;
                }
            }
        },
        computed:{
            filteredFiles: function() {
                var searchText = this.search;
                var result = [];
                if (searchText) {
                    result = _.pickBy(this.fileList, function (value, key) {
                        return _.includes(value.name, searchText) ||
                               _.includes(value.id, searchText) ||
                               _.includes(value.ownerName, searchText);
                    });
                }
                else{
                    result = this.fileList;
                }
                var orderNames = ['fileType'];
                var orderOrders = ['asc'];
                if (this.sortFieldName){
                    orderNames.push(this.sortFieldName);
                    orderOrders.push(this.sortFieldOrder);
                }
                result = _.orderBy(result, orderNames, orderOrders);
                return result;
            }
        },
        mounted() {
            this.sortFieldName = this.initSortField;
            this.sortFieldOrder = this.initSortOrder;
            //this.files = _.orderBy(this.fileList, ['fileType'], ['asc']);
        }
    }
</script>

<style>
    .sortable-header{
        cursor: pointer;
    }

    .sortable-header:hover{
        color: #2f4050;
    }
</style>