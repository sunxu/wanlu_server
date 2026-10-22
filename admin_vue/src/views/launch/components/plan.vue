
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
            <el-col :span="8">
                <span class="el-col-span">状态：</span>
                <el-select v-model="listQuery.status" class="filter-item" style="width: 240px;" size="small">
                    <el-option v-for="item in statusOption" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-col>
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
            <el-table-column prop="status" label="状态" width="100">
                <template slot-scope="scope">
                    <el-button type="text"
                        :icon="scope.row.status == 1 ? 'el-icon-star-on' :scope.row.status == 0 ? 'el-icon-star-off' : ''"
                        style="font-size:30px" @click="changeSwitch(scope.row,scope.row.status, scope.row.id)">
                    </el-button>
                </template>
            </el-table-column>
            <el-table-column label="广告计划id" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_id}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告计划名称" fixed align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ad_name}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主id" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_id}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告主名称" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.advertiser_name}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告组id" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.campaign_id}}</span>
                </template>
            </el-table-column>
            <el-table-column label="广告组名称" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.campaign_name}}</span>
                </template>
            </el-table-column>
            <el-table-column label="展现数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.show || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="点击数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.click || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="消耗" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.cost || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="提现金额（当日）" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.withdraw || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="ECPM-ROI" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.ecpm_roi || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装ROI" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装ROI(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_withdraw || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活ROI" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_roi || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活ROI(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_roi_withdraw || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装24h-ROI" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_24h || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装24h-ROI(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_roi_24h_withdraw || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活24h-ROI" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_roi_24h || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活24h-ROI(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_roi_24h_withdraw || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装预估收益" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_revenue || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装ARPU" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_avg_revenue || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活ARPU" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.new_active_arpu || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="老用户ARPU" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.old_arpu || 0}}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告人均展示" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_avg_show || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告人均展示(体内)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_avg_show_in || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告人均展示(体外)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_avg_show_out || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活广告人均展示" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_avg_show || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活广告人均展示(体内)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_avg_show_in || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="老用户广告人均展示" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_avg_show_out || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="老用户广告人均展示(体内)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.old_per_show || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="老用户广告人均展示(体外)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.old_avg_show_in || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.old_avg_show_out || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装次留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_num || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_next_day_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活次留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_num || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活回传数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_next_day_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激活回传次留率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_return_retention || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装成本(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_pre_cost || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活成本" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_withdraw || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活成本(提现)" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_pre_cost || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="激/安GAP" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_withdraw || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="回/激占比" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_install_gap || 0 }}%</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活七日留存数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.return_active_rate || 0 }}%</span>
                </template>
            </el-table-column>
            <el-table-column label="新增激活七日留存数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_seven_day || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.activate_seven_day_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告请求数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_num || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告填充数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_request || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告填充率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_fill || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告展示数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_fill_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告展示率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_show || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="新增安装广告点击数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_show_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告请求数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.install_click || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告填充数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_request || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告填充率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_fill || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告展示数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_fill_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告展示率" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_show || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="DEU广告点击数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_show_rate || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="百度锁屏展示数" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.active_click || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="更新时间" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.create_date || 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="回收时间" align="center">
                <template slot-scope="scope">
                    <span>{{ scope.row.update_date || 0 }}</span>
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
import { getAdvertis, getAdvertisPlan, planList, changePlanStatus } from '@/api/launch/lad'
import { getAppCode } from '@/utils/auth'

export default {
    label:'广告计划',
    name: 'ladplanTable',
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
                launch_type: undefined,
                status:undefined,
                type: '1',
                advertiser:undefined,
                plat: undefined,
                status: undefined,
                order: undefined,
                sort: undefined,
                page: 1,
                limit: 20,
                ad_id:undefined,
                oldLimit: 0,
                oldPage: 1
            },
            total: 0,
            adIdOption:[],
            platOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '巨量' }, { 'id': 2, 'name': '快手' }, { 'id': 7, 'name': '荣耀' }, { 'id': 8, 'name': '腾讯营销' }],
            advertiserOption: undefined,
            launch_typeOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '关键行为' }, { 'id': 2, 'name': '双出' }],
            statusOption: [{ 'id': '', 'name': '全部' }, { 'id': 1, 'name': '已标记' }, { 'id': 2, 'name': '未标记' }],
            startTimeOptions: { shortcuts },
            orderOption: [
                { 'id': '', 'name': '全部' },
                { 'id': 'show', 'name': '展现数' },
                { 'id': 'click', 'name': '点击数' },
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
                { 'id': 'install_fill_rate', 'name': '新增安装广告填充率' },
                { 'id': 'active_fill_rate', 'name': 'DEU广告填充率' },
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
            await planList(this.listQuery).then(response => {
                this.list = response.data.list
                if (this.list.length === 0) {
                    this.dataText = "暂无数据";
                }
                this.total = response.data.count;
                this.listQuery.page = response.data.page;
                this.listQuery.limit = response.data.limit;
                let td = response.data.total;
                let obj = {
                    ad_name: '合计',
                    status:3,
                    show: td.show_total,
                    click: td.click_total,
                    cost: td.cost_total,
                    withdraw: td.withdraw_total,
                    ecpm_roi: td.ecpm_roi_total,
                    install_roi: td.install_roi_total,
                    install_roi_withdraw: td.install_roi_withdraw_total,
                    activate_roi: td.activate_roi_total,
                    activate_roi_withdraw: td.activate_roi_withdraw_total,
                    install_roi_24h: td.install_roi_total24,
                    install_roi_24h_withdraw: td.install_roi_total24_withdraw,
                    activate_roi_24h: td.activate_roi_total24,
                    activate_roi_24h_withdraw: td.activate_roi_total24_withdraw,
                    install_revenue: td.install_revenue_total,
                    install_avg_revenue: td.install_avg_revenue_total,
                    new_active_arpu: td.new_active_arpu_total,
                    old_arpu: td.old_arpu_total,
                    install_avg_show: td.install_avg_show_total,
                    install_avg_show_in: td.install_show_in_total,
                    install_avg_show_out: td.install_show_out_total,
                    activate_avg_show: td.activate_avg_show_total,
                    activate_avg_show_in: td.activate_show_in_total,
                    activate_avg_show_out: td.activate_show_out_total,
                    old_per_show: td.old_per_show_total,
                    old_avg_show_in: td.old_show_in_total,
                    old_avg_show_out: td.old_show_out_total,
                    install_num: td.install_num_total,
                    install_next_day_rate: td.install_next_day_rate_total,
                    activate_num: td.activate_num_total,
                    activate_next_day_rate: td.activate_next_day_rate_total,
                    active: td.active_total,
                    activate_return_retention: td.activate_return_retention_total,
                    install_pre_cost: td.install_pre_cost_total,
                    install_withdraw: td.install_withdraw_total,
                    active_pre_cost: td.active_pre_cost_total,
                    activate_withdraw: td.activate_withdraw_total,
                    activate_install_gap: td.activate_install_gap_total,
                    return_active_rate: td.return_active_rate_total,
                    activate_seven_day: td.activate_seven_day_total,
                    activate_seven_day_rate: td.activate_seven_day_rate_total,
                    active_num: td.active_num_total,
                    install_request: td.install_request_total,
                    install_fill: td.install_fill_total,
                    install_fill_rate: td.install_fill_rate_total,
                    install_show: td.install_show_total,
                    install_show_rate: td.install_show_rate_total,
                    install_click: td.install_click_total,
                    active_request: td.active_request_total,
                    active_fill: td.active_fill_total,
                    active_fill_rate: td.active_fill_rate_total,
                    active_show: td.active_show_total,
                    active_show_rate: td.active_show_rate_total,
                    active_click: td.active_click_total,
                    active_lock_show: td.active_lock_show_total,
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
        selectChange() {
            let parames = {
                appcode: this.listQuery.appcode,
                advertiser:this.listQuery.advertiser
            };
            getAdvertisPlan(parames).then(res => {
                this.adIdOption = res.data.list
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
            // // 然后将整表数据条数赋值给分页大小，页码赋值为1，加载表格数据
            this.listQuery.page = 1;
            this.listQuery.limit = this.total;
            var that = this;
            this.getList().then(function () {
                import('@/vendor/Export2Excel').then(excel => {
                    const tHeader = ['日期', '状态', '计划id', '计划名称', '广告主ID', '广告主名称', '广告组id', '广告组名称', '展现数', '点击数', '消耗', '提现金额(当日)', 'ECPM-ROI', '新增安装ROI', '新增安装ROI(提现)', '新增激活ROI', '新增激活ROI(提现)', '新增安装24h-ROI', '新增安装24h-ROI(提现)', '新增激活24h-ROI', '新增激活24h-ROI(提现)', '新增安装预估收益', '新增安装ARPU', '新增激活ARPU', '老用户ARPU', '新增安装广告人均展示', '新增安装广告人均展示(体内)', '新增安装广告人均展示(体外)', '新增激活广告人均展示', '新增激活广告人均展示(体内)', '新增激活广告人均展示(体外)', '老用户广告人均展示', '老用户广告人均展示(体内)', '老用户广告人均展示(体外)', '新增安装数', '新增安装次留率', '新增激活数', '新增激活次留率', '激活回传数', '激活回传次留率', '新增安装成本', '新增安装成本(提现)', '新增激活成本', '新增激活成本(提现)', '激/安GAP', '回/激占比', '新增激活七日留存数', '新增激活七日留存率', 'DEU', '新增安装广告请求数', '新增安装广告填充数', '新增安装广告填充率', '新增安装广告展示数', '新增安装广告展示率', '新增安装广告点击数', 'DEU广告请求数', 'DEU广告填充数', 'DEU广告填充率', 'DEU广告展示数', 'DEU广告展示率', 'DEU广告点击数', '百度锁屏展示数', '更新时间', '回收时间']
                    const filterVal = ['date', 'status', 'ad_id', 'ad_name', 'advertiser_id', 'advertiser_name', 'campaign_id', 'campaign_name', 'show', 'click', 'cost', 'withdraw', 'ecpm_roi', 'install_roi', 'install_roi_withdraw', 'activate_roi', 'activate_roi_withdraw', 'install_roi_24h', 'install_roi_24h_withdraw', 'activate_roi_24h', 'activate_roi_24h_withdraw', 'install_revenue', 'install_avg_revenue', 'new_active_arpu', 'old_arpu', 'install_avg_show', 'install_avg_show_in', 'install_avg_show_out', 'activate_avg_show', 'activate_avg_show_in', 'activate_avg_show_out', 'old_per_show', 'old_avg_show_in', 'old_avg_show_out', 'install_num', 'install_next_day_rate', 'activate_num', 'activate_next_day_rate', 'active', 'activate_return_retention', 'install_pre_cost', 'install_withdraw', 'active_pre_cost', 'activate_withdraw', 'activate_install_gap', 'return_active_rate', 'activate_seven_day', 'activate_seven_day_rate', 'active_num', 'install_request', 'install_fill', 'install_fill_rate', 'install_show', 'install_show_rate', 'install_click', 'active_request', 'active_fill', 'active_fill_rate', 'active_show', 'active_show_rate', 'active_click', 'active_lock_show', 'create_date', 'update_date']
                    const data = that.formatJson(filterVal)
                    excel.export_json_to_excel({
                        header: tHeader,
                        data,
                        filename: '广告计划' + that.listQuery.startTime[0]
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
