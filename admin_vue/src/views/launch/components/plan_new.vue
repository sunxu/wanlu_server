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
                <el-select v-model="listQuery.plat" class="filter-item" style="width: 240px;" size="small">
                    <el-option v-for="item in platOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">投放方式：</span>
                <el-select v-model="listQuery.launch_type" class="filter-item" style="width: 240px;" size="small">
                    <el-option v-for="item in launch_typeOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <!-- <el-col :span="8">
                <span class="el-col-span">状态：</span>
                <el-select v-model="listQuery.status" class="filter-item" style="width: 240px;" size="small">
                    <el-option v-for="item in statusOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col> -->
            <el-col :span="8">
                <span class="el-col-span">排序类型：</span>
                <el-select v-model="listQuery.order" placeholder="排序类型" style="width: 240px;" size="small"
                    class="filter-item">
                    <el-option v-for="item in orderOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">排序方式：</span>
                <el-select v-model="listQuery.sort" placeholder="排序方式" style="width: 240px;" size="small"
                    class="filter-item">
                    <el-option v-for="item in sortOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="8">
                <span class="el-col-span">广告主：</span>
                <el-select v-model="listQuery.advertiser" class="filter-item" style="width: 240px;" size="small"
                    filterable multiple exportStatistics @change="selectChange">
                    <el-option v-for="item in advertiserOption" :key="item.advertiser_id" :label="item.advertiser_id"
                        :value="item.advertiser_id">
                        <span style="float: left">{{ item.advertiser_id }}</span>
                        <span style="float: right; color: #8492a6; font-size: 13px">{{ item.advertiser_name }}</span>
                    </el-option>
                </el-select>
            </el-col>
            <el-col :span="8">
                <span class="el-col-span">广告计划：</span>
                <el-select v-model="listQuery.ad_id" class="filter-item" filterable multiple style="width: 240px;"
                    size="small" exportStatistics>
                    <el-option v-for="(item, itemIndex) in adIdOption" :key="itemIndex" :label="`${itemIndex}`"
                        :value="itemIndex">
                        <span style="float: left">{{ item }}</span>
                        <span style="float: right; color: #8492a6; font-size: 13px">{{ itemIndex }}</span>
                    </el-option>
                </el-select>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12" style="float: right; ">
                <el-button v-waves :loading="downloadLoading" style="float: right; " class="filter-item" type="primary" icon="el-icon-download" @click="handleDownload">导出</el-button>
                <el-button v-waves class="filter-item" type="primary" style="float: right; " icon="el-icon-search"
                    @click="handleFilter">搜索
                </el-button>
            </el-col>
        </el-row>


        <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row
            :span-method="arraySpanMethod">
            <template slot="empty">
                <p>{{dataText}}</p>
            </template>
            <el-table-column label="日期" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.date }}</span>
                </template>
            </el-table-column>
            <!-- <el-table-column prop="status" label="状态" width="100">
                <template slot-scope="scope">
                    <el-button type="text"
                        :icon="scope.row.status == 1 ? 'el-icon-star-on' :scope.row.status == 0 ? 'el-icon-star-off' : ''"
                        style="font-size:30px" @click="changeSwitch(scope.row,scope.row.status, scope.row.id)">
                    </el-button>
                </template>
            </el-table-column> -->

            <el-table-column label="计划id" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_id || ''}}</span>
                </template>
            </el-table-column>
            <el-table-column label="计划名称" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_name || ''}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主id" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_id || ''}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主名称" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_name || ''}}</span>
                </template>
            </el-table-column>

            <!-- <el-table-column label="组id" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.campaign_id || ''}}</span>
                </template>
            </el-table-column>
            <el-table-column label="组名称" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.campaign_name || ''}}</span>
                </template>
            </el-table-column> -->
            <!-- <el-table-column label="广告投放时间" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.schedule_start || ''}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告投放结束时间" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.schedule_end || ''}}</span>
                </template>
            </el-table-column> -->
            <el-table-column label="消耗" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.cost || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="提现金额(当日)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.withdraw || 0}}</span>
                </template>
            </el-table-column>
          <el-table-column label="充值(当日)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_payment || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_num || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="安装成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_pre_cost || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="提现安装成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_withdraw || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装arpu" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_avg_revenue || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装roi" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="(充值)新增安装roi" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_payment || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="(提现)新增安装roi" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_withdraw || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="24h新增安装roi" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_24h || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="(提现)24h新增安装roi" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_24h_withdraw || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_cost || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="关键行为成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.key_action_cost || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="关键行为率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.key_action_rate || 0}}%</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活回传数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="关键行为数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.key_action || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="3日回本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery3_rate || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="7日回本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery7_rate || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="10日回本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.recovery10_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活次留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep1_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活3留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep3_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活7留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep7_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活10留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.keep10_rate || 0 }}</span>
                </template>
            </el-table-column>
        </el-table>
        <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.limit"
            @pagination="getList" />
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
import { getAdvertis, getAdvertisPlan, planListNew, changePlanStatus } from '@/api/launch/lad'
import { getAppCode } from '@/utils/auth'

export default {
    label: '新广告投放数据',
    name: 'ladnewTable',
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
                status: undefined,
                type: '1',
                advertise: undefined,
                plat: undefined,
                status: undefined,
                order: undefined,
                sort: undefined,
                page: 1,
                limit: 20,
                ad_id: undefined,
                oldLimit: 0,
                oldPage: 1
            },
            total: 0,
            adIdOption: [],
            platOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '巨量' }, { 'id': 2, 'name': '快手' }, { 'id': 7, 'name': '荣耀' }, { 'id': 8, 'name': '腾讯营销' }],
            advertiserOption: undefined,
            launch_typeOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '关键行为' }, { 'id': 2, 'name': '双出' }],
            statusOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '已标记' }, { 'id': 2, 'name': '未标记' }],
            startTimeOptions: { shortcuts },
            orderOption: [
            { 'id': '', 'name': '全部' },
                // { 'id': 'show', 'name': '展现数' },
                // { 'id': 'click', 'name': '点击数' },
                { 'id': 'cost', 'name': '消耗' },
                { 'id': 'withdraw', 'name': '提现金额(当日)' },
                { 'id': 'install_roi', 'name': '新增安装ROI' },
                { 'id': 'install_roi_withdraw', 'name': '新增安装ROI(提现)' },
                { 'id': 'install_roi_24h', 'name': '新增安装24h - ROI' },
                { 'id': 'activate_roi_24h', 'name': '新增激活24h - ROI' },
                { 'id': 'install_revenue', 'name': '新增安装预估收益' },
                { 'id': 'install_num', 'name': '新增安装数' },
                { 'id': 'activate_num', 'name': '新增激活数' },
                { 'id': 'active', 'name': '激活回传数' },
                { 'id': 'install_pre_cost', 'name': '新增安装成本' },
                { 'id': 'install_withdraw', 'name': '新增安装成本(提现)' },
                { 'id': 'active_pre_cost', 'name': '新增激活成本' },
                { 'id': 'activate_withdraw', 'name': '新增激活成本(提现)' },
                { 'id': 'active_num', 'name': 'DEU' },
                // { 'id': 'install_fill_rate', 'name': '新增安装广告填充率' },
                // { 'id': 'active_fill_rate', 'name': 'DEU广告填充率' },
            ],
            sortOption: [
                { 'id': '', 'name': '全部' },
                { 'id': '1', 'name': '升序' },
                { 'id': '2', 'name': '降序' },
            ],
            downloadLoading: false
        }
    },
    created() {
        this.advertiser();
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
        async getList() {
            this.listLoading = true
            await planListNew(this.listQuery).then(response => {
                this.list = response.data.list
                if (this.list.length === 0) {
                    this.dataText = "暂无数据";
                }
                this.total = response.data.count;
                this.listQuery.page = response.data.page;
                this.listQuery.limit = response.data.limit;
                let td = response.data.total;
                let obj = {
                    advertiser_name: '合计',
                    status:3,
                    cost: td.cost_total,
                  install_payment: td.install_payment_total,
                    withdraw: td.withdraw_total,
                    install_num: td.install_num_total,
                    install_pre_cost: td.install_pre_cost_total,
                    install_withdraw: td.install_withdraw_total,
                    install_avg_revenue: td.install_avg_revenue_total,
                    install_roi: td.install_roi_total,
                    install_roi_payment: td.install_roi_payment_total,
                    install_roi_withdraw: td.install_roi_withdraw_total,
                    install_roi_24h: td.install_roi_total24,
                    install_roi_24h_withdraw: td.install_roi_total24_withdraw,
                    active_cost: td.active_cost_total,
                    key_action_cost: td.key_action_cost_total,
                    key_action_rate: td.key_action_rate_total,
                    active: td.active_total,
                    key_action: td.key_action_num_total,
                    recovery3_rate: td.recovery3_rate_total,
                    recovery7_rate: td.recovery7_rate_total,
                    recovery10_rate: td.recovery10_rate_total,
                    keep1_rate: td.keep1_rate_total,
                    keep3_rate: td.keep3_rate_total,
                    keep7_rate: td.keep7_rate_total,
                    keep10_rate: td.keep10_rate_total,
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
        selectChange() {
            let parames = {
                appcode: this.listQuery.appcode,
                advertiser: this.listQuery.advertise
            };
            getAdvertisPlan(parames).then(res => {
                this.adIdOption = res.data.list
            })
        },
        changeSwitch(row, last_status, id) {
            if (id > 0) {
                const params = {
                    id: id,
                    status: last_status == 0 ? 1 : 0,
                }
                //调用后台修改接口
                changePlanStatus(params).then(res => {
                    if (res.code == 0) {
                        row.status = params.status;
                        this.$message({
                            message: '修改成功',
                            type: 'success'
                        })
                    } else {
                        this.$message({
                            message: '修改失败',
                            type: 'error'
                        })
                    }
                })
            } else {
                this.$message({
                    message: '切换该状态无效',
                    type: 'error'
                })
            }


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
            // 因为分页是通过后端返回的，这里是保存当前的分页大小和页码
            this.listQuery.oldLimit = this.listQuery.limit;
            this.listQuery.oldPage = this.listQuery.page;
            // 然后将整表数据条数赋值给分页大小，页码赋值为1，加载表格数据
            this.listQuery.page = 1;
            this.listQuery.limit = this.total;
            var that = this;
            this.getList().then(function () {
                import('@/vendor/Export2Excel').then(excel => {
                    const tHeader = ['日期','广告主ID', '广告主名称', '广告计划ID', '广告计划名称', '消耗', '提现金额(当日)', '新增安装', '安装成本', '提现安装成本', '新增安装arpu', '新增安装roi','(充值)新增安装roi', '(提现)新增安装roi', '24h新增安装roi', '(提现)24h新增安装roi', '激活成本', '关键行为成本', '达成率', '新增激活回传数', '关键行为数', '3日回本', '7日回本', '10日回本', '激活回传次留率', '激活回传3留率', '激活回传7留率', '激活回传10留率']
                    const filterVal = ['date', 'advertiser_id', 'advertiser_name', 'ad_id', 'ad_name','cost', 'withdraw', 'install_num', 'install_pre_cost', 'install_withdraw', 'install_avg_revenue', 'install_roi','install_roi_payment', 'install_roi_withdraw', 'install_roi_24h', 'install_roi_24h_withdraw', 'active_cost', 'key_action_cost', 'key_action_rate', 'active', 'key_action', 'recovery3_rate', 'recovery7_rate', 'recovery10_rate', 'keep1_rate', 'keep3_rate', 'keep7_rate', 'keep10_rate']
                    const data = that.formatJson(filterVal)
                    excel.export_json_to_excel({
                        header: tHeader,
                        data,
                        filename: '统计数据广告计划' + that.listQuery.startTime[0]
                    })
                    that.downloadLoading = false
                    that.listQuery.limit = that.listQuery.oldLimit;
                    that.listQuery.page = that.listQuery.oldPage;
                    that.getList();
                })
            });

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
