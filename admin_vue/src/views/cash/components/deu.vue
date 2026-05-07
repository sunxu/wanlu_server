<template>
    <div class="app-container">
        <div class="filter-container">
            <el-date-picker v-model="listQuery.startTime" type="daterange" align="right" unlink-panels
                range-separator="至" start-placeholder="开始日期1" end-placeholder="结束日期1" value-format="yyyy-MM-dd"
                :picker-options="startTimeOptions">
            </el-date-picker>
            产品版本222：<el-select v-model="listQuery.all_version" placeholder="全版本" filterable multiple exportStatistics
                clearable style="width: 90px" class="filter-item">
                <el-option v-for="item in all_version" :key="item" :label="item" :value="item" />
            </el-select>
            产品渠道：<el-select v-model="listQuery.channel" placeholder="产品渠道" clearable filterable multiple
                exportStatistics class="filter-item" style="width: 130px">
                <el-option v-for="item in channelOption" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
            产品厂商：<el-select v-model="listQuery.vendor" style="width: 140px" filterable multiple exportStatistics
                class="filter-item">
                <el-option v-for="item in vendorOption" :key="item" :label="item" :value="item" />
            </el-select>
            <el-button v-waves class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">搜索
            </el-button>
            <el-button v-waves :loading="downloadLoading" class="filter-item" type="primary" icon="el-icon-download"
                @click="handleDownload">导出</el-button>
        </div>
        <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row
            style="width: 100%;">
            <el-table-column label="日期" fixed align="center">
                <template slot-scope="{row}">
                    <span>{{ row.day_time }}</span>
                </template>
            </el-table-column>
            <el-table-column label="deu" fixed align="center">
                <template slot-scope="{row}">
                    <span>{{ row.allNum || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="华为" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.huawei || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.huaweiProportion || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.huawei7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.huawei8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.huawei9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="oppo" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.oppo || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.oppoProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.oppo7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.oppo8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.oppo9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="vivo" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.vivo || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.vivoProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.vivo7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.vivo8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.vivo9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="小米" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.xiaomi || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.xiaomiProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.xiaomi7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.xiaomi8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.xiaomi9 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="其他" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.other || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.otherProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.other7 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.other8 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上" align="center">
                <template slot-scope="{row}">
                    <span>{{ row.other9 || 0 }}</span>
                </template>
            </el-table-column>
        </el-table>

    </div>
</template>

<script>
import waves from '@/directive/waves' // waves directive
import { parseTime } from '@/utils'
import Pagination from '@/components/Pagination' // secondary package based on el-pagination
import { getChannel,cashList } from '@/api/cash/list'


export default {
    name: 'deuTable',
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

            tableKey: 0,
            list: null,
            listLoading: true,
            listQuery: {
                startTime: [new Date(), new Date()],
                all_version: '全部渠道',
                channel: undefined,
                vendor: '全部厂商',
                appcode: 'wifi',
                type:'deu'
            },
            channelOption:undefined,
            all_version: ['全部','1.0.4', '1.0.3', '1.0.2', '1.0.1', '1.0.0'],
            vendorOption: ['vivo', 'HUAWEI', 'Xiaomi', 'OPPO'],
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
            temp: {
                id: undefined,
                importance: 1,
                remark: '',
                timestamp: new Date(),
            },
            downloadLoading: false
        }
    },
    created() {
        this.getList()
    },
    mounted() {
        this.channel();
    },
    methods: {
        getList() {
            this.listLoading = true
            cashList(this.listQuery).then(response => {
                this.list = response.data
                this.listLoading = false
                // Just to simulate the time of the request
                // setTimeout(() => {
                //     this.listLoading = false
                // }, 1.5 * 1000)
            })
        },
        async channel() {
            let res = await getChannel()
            this.channelOption = res.data;
        },
        sortByID(order) {
            if (order === 'ascending') {
                this.listQuery.sort = '+id'
            } else {
                this.listQuery.sort = '-id'
            }
            this.handleFilter()
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
                const tHeader = ['日期', 'deu', '华为', '占比', 'android8以下', 'android8-10', 'android11以上', '小米', '占比', 'android8以下', 'android8-10', 'android11以上', 'vivo', '占比', 'android8以下', 'android8-10', 'android11以上', 'oppo', '占比', 'android8以下', 'android8-10', 'android11以上', '其他', '占比', 'android8以下', 'android8-10', 'android11以上',]
                const filterVal = ['day_time', 'allNum', 'huawei', 'huaweiProportion', 'huawei7', 'huawei8', 'huawei9', 'xiaomi', 'xiaomiProportion', 'xiaomi7', 'xiaomi8', 'xiaomi9', 'vivo', 'vivoProportion', 'vivo7', 'vivo8', 'vivo9', 'oppo', 'oppoProportion', 'oppo7', 'oppo8', 'oppo9', 'other', 'otherProportion', 'other7', 'other8', 'other9',]
                const data = this.formatJson(filterVal)
                excel.export_json_to_excel({
                    header: tHeader,
                    data,
                    filename: 'deu' + this.listQuery.startTime[0]
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
