<template>
    <div class="app-container">
        <el-row>
            <el-col :span="8">
                <el-date-picker v-model="listQuery.startTime" type="daterange" align="right" unlink-panels
                range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="yyyy-MM-dd"
                :picker-options="startTimeOptions">
                </el-date-picker>
            </el-col>
            <el-col :span="8">
                <el-button v-waves class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索
            </el-button>
            <el-button v-waves :loading="downloadLoading" class="filter-item" type="primary" icon="el-icon-download"
                @click="handleDownload">导出</el-button>
            </el-col>
        </el-row>
        <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row>
            <template slot="empty">
                <p>{{dataText}}</p>
            </template>
            <el-table-column label="日期" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.date }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="无任意广告展示" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.level_none || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.rate_none || 0 }}%</span>
                </template>
            </el-table-column>
            <el-table-column label="只有兜底层广告展示" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.level_0 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.rate_zero || 0}}%</span>
                </template>
            </el-table-column>
            <el-table-column label="只有兜底层和保价倒1层" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.level_1 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.rate_one || 0 }}%</span>
                </template>
            </el-table-column>
            <el-table-column label="只有兜底层和保价倒2层" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.level_2 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.rate_two || 0}}%</span>
                </template>
            </el-table-column>
        </el-table>
        <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit"
            @pagination="getList" />
    </div>
</template>

<script>
import waves from '@/directive/waves' // waves directive
import { parseTime } from '@/utils'
import Pagination from '@/components/Pagination' // secondary package based on el-pagination
import { ratioList } from '@/api/target/ratio'
import { getAppCode } from '@/utils/auth'

export default {
    label:'指标比例',
    name: 'ratio',
    components: { Pagination },
    directives: { waves },
    filters: {
        statusFilter(status) {
            const statusMap = {
                published: 'success',
                draft: 'info',
                deleted: 'danger'
            }
            return statusMap[status]
        }
    },
    data() {
        return {
            label:'1',
            dataText:'暂无数据',
            tableKey: 0,
            list: null,
            listLoading: true,
            listQuery: {
                startTime: this.defaultDate(),
                appcode: getAppCode(),
                page: 1,
                limit: 10,
                oldLimit: 0,
                oldPage: 1
            },
            total:0,
            startTimeOptions: {
                shortcuts: [{
                    text: '最近2天',
                    onClick(picker) {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 2);
                        picker.$emit('pick', [start, end]);
                    }
                }, {
                    text: '最近一周',
                    onClick(picker) {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                        picker.$emit('pick', [start, end]);
                    }
                }, {
                    text: '最近两周',
                    onClick(picker) {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 14);
                        picker.$emit('pick', [start, end]);
                    }
                }]
            },
            downloadLoading: false
        }
    },
    created() {
        this.getList()
    },
    methods: {
        defaultDate() {//默认时间 - 过去7天
            const end = new Date()
            const start = new Date()
            end.setTime(end.getTime())
            start.setTime(start.getTime() - 86400 * 1000 * 7)
            const format = '{y}-{m}-{d}'
            return [parseTime(start, format), parseTime(end, format)]
        },
        async getList() {
            this.listLoading = true
            await ratioList(this.listQuery).then(response => {
                this.list = response.data.list
                if (this.list.length === 0) {
                    this.dataText = "暂无数据";
                }
                this.total = response.data.total;
                this.listQuery.page = response.data.page;
                this.listQuery.limit = response.data.limit;
                this.listLoading = false
            }).catch(() => {
                this.listLoading = false
            })
        },
        handleFilter() {
            this.getList()
        },
        handleDownload() {
            this.downloadLoading = true
            // 因为分页是通过后端返回的，这里是保存当前的分页大小和页码
            this.listQuery.oldLimit = this.listQuery.limit;
            this.listQuery.oldPage = this.listQuery.page;
            // 然后将整表数据条数赋值给分页大小，页码赋值为1，加载表格数据
            this.listQuery.page = 1;
            this.listQuery.limit = this.total;
            var that = this;
            this.getList().then(function(){
                import('@/vendor/Export2Excel').then(excel => {
                    const tHeader = ['日期', '新增安装数', '新增激活数', '无任意广告展示', '占比', '只有兜底层广告展示', '占比', '只有兜底层和保价倒1层', '占比', '只有兜底层和保价倒2层', '占比']
                    const filterVal = ['date', 'install', 'activate', 'level_none', 'rate_none', 'level_0', 'rate_zero', 'level_1', 'rate_one', 'level_2', 'rate_two']
                    const data = that.formatJson(filterVal)
                    excel.export_json_to_excel({
                        header: tHeader,
                        data,
                        filename: '指标比例' + that.listQuery.startTime[0]
                    })
                    that.downloadLoading = false
                    that.listQuery.limit = that.listQuery.oldLimit;
                    that.listQuery.page = that.listQuery.oldPage;
                    that.getList();
                })
            });
        },
        formatJson(filterVal) {
            return this.list.map(v => filterVal.map(j => {
                if (j === 'timestamp') {
                    return parseTime(v[j])
                } else {
                    return v[j]
                }
            }))
        },
        getSortClass: function (key) {
            const sort = this.listQuery.sort
            return sort === `+${key}` ? 'ascending' : 'descending'
        }
    }
}
</script>
