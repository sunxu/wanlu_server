
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
                <span class="el-col-span">版本：</span>
                <el-input v-model="listQuery.all_version" placeholder="版本" size="small" style="width: 240px;"
                    class="filter-item" />
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">产品渠道：</span>
                <el-select v-model="listQuery.channel" placeholder="产品渠道" clearable style="width: 240px;"
                        class="filter-item">
                <el-option label="全部" value=""></el-option>
                <el-option
                    v-for="(value,key) in $global.CHANNEL_MAP"
                    :label="value"
                    :value="key"
                ></el-option>
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="8">
                <span class="el-col-span">产品厂商：</span>
                <el-select v-model="listQuery.vendor" style="width: 240px;" class="filter-item">
                <el-option label="全部" value=""></el-option>
                <el-option
                    v-for="(value,key) in $global.VENDOR_MAP"
                    :label="value"
                    :value="key"
                ></el-option>
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <el-radio-group v-model="listQuery.type" size="small" @change="handleFilter">
                    <el-radio-button label="1">新增安装</el-radio-button>
                    <el-radio-button label="5">DAU</el-radio-button>
                </el-radio-group>
            </el-col>
            <el-col :span="12">
                <el-button v-waves :loading="downloadLoading" style="float: right; " class="filter-item" type="primary"
                    icon="el-icon-download" @click="handleDownload">导出</el-button>
                <el-button v-waves class="filter-item" type="primary" icon="el-icon-search"
                    style="float: right;margin-right: 10px;" @click="handleFilter">搜索
                </el-button>
            </el-col>
        </el-row>

        <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row
            style="width: 100%;">
            <template slot="empty">
                <p>{{dataText}}</p>
            </template>
            <el-table-column label="日期" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.day_time }}</span>
                </template>
            </el-table-column>
            <el-table-column label="设备数" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.allNum || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="华为" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.huawei || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.huaweiProportion || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.huawei7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.huawei8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.huawei9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="oppo" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.oppo || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.oppoProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.oppo7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.oppo8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.oppo9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="vivo" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.vivo || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.vivoProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.vivo7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.vivo8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.vivo9 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="小米" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.xiaomi || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.xiaomiProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.xiaomi7 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.xiaomi8 || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.xiaomi9 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="其他" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.other || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.otherProportion || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8以下(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.other7 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓8-10(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.other8 || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="安卓11及以上(分钟)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.other9 || 0 }}</span>
                </template>
            </el-table-column>
        </el-table>

    </div>
</template>
<style>
.el-row {
    margin-bottom: 10px;
}

.el-radio {
    margin: 0
}

.el-col-span {
    display: inline-block;
    width: 80px;
    padding: 6px 0;
    font-size: 14px;
}
</style>
<script>
import waves from '@/directive/waves' // waves directive
import { parseTime } from '@/utils'
import shortcuts from '@/utils/date-picker'
import Pagination from '@/components/Pagination' // secondary package based on el-pagination
import { cashList } from '@/api/cash/list'
import { getAppCode } from '@/utils/auth'


export default {
    label: '新增安装',
    name: 'installTable',
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
                all_version: undefined,
                channel: undefined,
                vendor: '',
                appcode: getAppCode(),
                type: '1',
                adScene: undefined,
            },
            appcodeOption: undefined,
            channelOption: undefined,
            // all_version: ['全部','1.0.4', '1.0.3', '1.0.2', '1.0.1', '1.0.0'],
            vendorOption: ['vivo', 'HUAWEI', 'Xiaomi', 'OPPO', 'other'],
            startTimeOptions: { shortcuts },
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
    mounted() {},
    methods: {
        defaultDate() {//默认时间 - 过去7天
            const end = new Date()
            const start = new Date()
            end.setTime(end.getTime() - 86400 * 1000)
            start.setTime(start.getTime() - 86400 * 1000 * 3)
            const format = '{y}-{m}-{d}'
            return [parseTime(start, format), parseTime(end, format)]
        },
        getList() {
            this.listLoading = true
            cashList(this.listQuery).then(response => {
                this.list = response.data.list
                this.listLoading = false
                if (this.list.length === 0) {
                    this.dataText = "暂无数据";
                }
                // Just to simulate the time of the request
                // setTimeout(() => {
                //     this.listLoading = false
                // }, 1.5 * 1000)
            }).catch(() => {
                this.listLoading = false
            })
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
            // switch (this.listQuery.type) {
            //     case "1": this.label = '新增安装'; break;
            //     case "2": this.label = 'deu'; break;
            // }
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
                const tHeader = ['日期', '设备数', '华为', '占比', 'android8以下(分钟)', 'android8-10(分钟)', 'android11以上(分钟)', '小米', '占比', 'android8以下(分钟)', 'android8-10(分钟)', 'android11以上(分钟)', 'vivo', '占比', 'android8以下(分钟)', 'android8-10(分钟)', 'android11以上(分钟)', 'oppo', '占比', 'android8以下(分钟)', 'android8-10(分钟)', 'android11以上(分钟)', '其他', '占比', 'android8以下(分钟)', 'android8-10(分钟)', 'android11以上(分钟)',]
                const filterVal = ['day_time', 'allNum', 'huawei', 'huaweiProportion', 'huawei7', 'huawei8', 'huawei9', 'xiaomi', 'xiaomiProportion', 'xiaomi7', 'xiaomi8', 'xiaomi9', 'vivo', 'vivoProportion', 'vivo7', 'vivo8', 'vivo9', 'oppo', 'oppoProportion', 'oppo7', 'oppo8', 'oppo9', 'other', 'otherProportion', 'other7', 'other8', 'other9',]
                const data = this.formatJson(filterVal)
                excel.export_json_to_excel({
                    header: tHeader,
                    data,
                    filename: this.label + this.listQuery.startTime[0]
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
