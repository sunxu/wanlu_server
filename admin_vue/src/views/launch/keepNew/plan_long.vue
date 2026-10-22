<template>
    <div class="app-container">
        <el-row>
            <el-col :span="8">
                <span class="el-col-span">日期：</span>
                <el-date-picker v-model="listQuery.startTime" type="daterange" align="right" unlink-panels
                    range-separator="~" style="width: 240px;" size="small" start-placeholder="开始日期"
                    end-placeholder="结束日期" value-format="yyyy-MM-dd" :picker-options="startTimeOptions">
                </el-date-picker>
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">投放平台：</span>
                <el-select v-model="listQuery.plat" placeholder="投放平台" class="filter-item" size="small"
                    style="width: 240px;">
                    <el-option v-for="item in platOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">投放方式：</span>
                <el-select v-model="listQuery.launch_type" placeholder="投放方式" class="filter-item" size="small"
                    style="width: 240px;">
                    <el-option v-for="item in launch_typeOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="8">
                <span class="el-col-span">广告主：</span>
                <el-select v-model="listQuery.advertiser" placeholder="广告主" class="filter-item" filterable multiple
                    exportStatistics size="small" style="width: 240px;">
                    <el-option v-for="item in advertiserOption" :key="item.advertiser_id" :label="item.advertiser_id"
                        :value="item.advertiser_id">
                        <span style="float: left">{{ item.advertiser_id }}</span>
                        <span style="float: right; color: #8492a6; font-size: 13px">{{ item.advertiser_name }}</span>
                    </el-option>
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <el-button v-waves :loading="downloadLoading" style="float: right; " class="filter-item" type="primary" icon="el-icon-download" @click="handleDownload">导出</el-button>
                <el-button v-waves class="filter-item" type="primary" icon="el-icon-search"
                    style="float: right;margin-right: 10px;" @click="handleFilter">搜索
                </el-button>
            </el-col>
        </el-row>
        <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row
            :span-method="arraySpanMethod">
            <template slot="empty">
                <p>{{ dataText }}</p>
            </template>
            <el-table-column label="日期" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.date }}</span>
                </template>
            </el-table-column>
            <el-table-column label="计划id" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_id || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="计划名称" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_name || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主id" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_id || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主id" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_id || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主名称" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_name || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_num || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_num || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活回传数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="次留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep1_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="2留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep2_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="3留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep3_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="4留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep4_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="5留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep5_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="6留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep6_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="7留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep7_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="回本率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery0_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="次日回本率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery1_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="2日回本率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery2_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="3日回本率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery3_rate || 0 }}</span>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script>
import waves from '@/directive/waves' // waves directive
import { parseTime } from '@/utils'
import shortcuts from '@/utils/date-picker'
import Pagination from '@/components/Pagination' // secondary package based on el-pagination
import { getAdvertis, longPlan } from '@/api/launch/lad'
import { getAppCode } from '@/utils/auth'

export default {
    label: '广告计划激活数据',
    name: 'longPlan',
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
            label: '1',
            dataText: '暂无数据',
            tableKey: 0,
            list: null,
            listLoading: true,
            listQuery: {
                startTime: this.defaultDate(),
                appcode: getAppCode(),
                launch_type: undefined,
                advertise: undefined,
                plat: undefined
            },
            platOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '巨量' }, { 'id': 2, 'name': '快手' }, { 'id': 8, 'name': '腾讯营销' }],
            advertiserOption: undefined,
            launch_typeOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '关键行为' }, { 'id': 2, 'name': '双出' }],
            statusOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '已标记' }, { 'id': 2, 'name': '未标记' }],
            startTimeOptions: { shortcuts },
            downloadLoading: false
        }
    },
    created() {
        this.advertiser()
        this.getList()
    },
    methods: {
        defaultDate() {//默认时间 - 过去7天
            const end = new Date()
            const start = new Date()
            end.setTime(end.getTime() - 86400 * 1000)
            start.setTime(start.getTime() - 86400 * 1000 * 1)
            const format = '{y}-{m}-{d}'
            return [parseTime(start, format), parseTime(end, format)]
        },
        getList() {
            this.listLoading = true
            longPlan(this.listQuery).then(response => {
                this.list = response.data.list
                if (this.list.length === 0) {
                    this.dataText = "暂无数据";
                }
                let td = response.data.total;
                let obj = {
                    date: '',
                    ad_id: '',
                    ad_name:'合计',
                    advertiser_id: '',
                    advertiser_name: '',
                    install_num: td.total_install_num,
                    activate_num: td.total_activate_num,
                    active: td.total_active,
                    keep1_rate: td.keep1_rate,
                    keep2_rate: td.keep2_rate,
                    keep3_rate: td.keep3_rate,
                    keep4_rate: td.keep4_rate,
                    keep5_rate: td.keep5_rate,
                    keep6_rate: td.keep6_rate,
                    keep7_rate: td.keep7_rate,
                    recovery0_rate: td.recovery0_rate,
                    recovery1_rate: td.recovery1_rate,
                    recovery2_rate: td.recovery2_rate,
                    recovery3_rate: td.recovery3_rate,
                    recovery4_rate: td.recovery4_rate,
                    recovery5_rate: td.recovery5_rate,
                    recovery6_rate: td.recovery6_rate,
                    recovery7_rate: td.recovery7_rate
                }
                this.summaryFun(obj)
                this.listLoading = false
            }).catch(() => {
                this.listLoading = false
            })
        },
        summaryFun(obj) {
            var obj = obj;
            this.list.unshift(obj);
        },
        advertiser() {
            getAdvertis(this.listQuery).then(res => {
                this.advertiserOption = res.data.list
            })
        },

        arraySpanMethod({ row, column, rowIndex, columnIndex }) {
            if (rowIndex === 0) {
                if (columnIndex === 0) {
                    return [0, 0];
                } else if (columnIndex === 1) {
                    return [1, 2];
                }
            }
        },

        handleFilter() {
            this.getList()
        },
        resetTemp() {
            this.temp = {
                id: undefined,
                importance: 1,
                remark: '',
                timestamp: new Date(),
                title: '',
                status: 'published',
                type: ''
            }
        },
        handleCreate() {
            this.resetTemp()
            this.dialogStatus = 'create'
            this.dialogFormVisible = true
            this.$nextTick(() => {
                this.$refs['dataForm'].clearValidate()
            })
        },
        handleDownload() {
            this.downloadLoading = true
            import('@/vendor/Export2Excel').then(excel => {
                const tHeader = ['日期', '计划id', '计划名', '广告主ID', '广告主名称', '新增安装数', '新增激活数', '激活回传数', '次留率', '2留率', '3留率', '4留率', '5留率', '6留率', '7留率', '回本率', '次日回本率', '2日回本率', '3日回本率']
                const filterVal = ['date', 'ad_id','ad_name','advertiser_id', 'advertiser_name',  'install_num', 'activate_num', 'active', 'keep1_rate', 'keep2_rate', 'keep3_rate', 'keep4_rate', 'keep5_rate', 'keep6_rate', 'keep7_rate', 'recovery0_rate', 'recovery1_rate', 'recovery2_rate', 'recovery3_rate']
                const data = this.formatJson(filterVal)
                excel.export_json_to_excel({
                    header: tHeader,
                    data,
                    filename: '激活留存广告计划' + this.listQuery.startTime[0]
                })
                this.downloadLoading = false
            })
        },
        createData() {
            this.$refs['dataForm'].validate((valid) => {
                if (valid) {
                    this.temp.id = parseInt(Math.random() * 100) + 1024 // mock a id
                    this.temp.author = 'vue-element-admin'
                    createArticle(this.temp).then(() => {
                        this.list.unshift(this.temp)
                        this.dialogFormVisible = false
                        this.$notify({
                            title: 'Success',
                            message: 'Created Successfully',
                            type: 'success',
                            duration: 2000
                        })
                    })
                }
            })
        },
        handleUpdate(row) {
            this.temp = Object.assign({}, row) // copy obj
            this.temp.timestamp = new Date(this.temp.timestamp)
            this.dialogStatus = 'update'
            this.dialogFormVisible = true
            this.$nextTick(() => {
                this.$refs['dataForm'].clearValidate()
            })
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
