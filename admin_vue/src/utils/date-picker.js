export default [{
  text: '今天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    picker.$emit('pick', [start, end])
  }
}, {
  text: '昨天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    end.setTime(end.getTime() - 86400 * 1000)
    start.setTime(start.getTime() - 86400 * 1000)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '近三天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    start.setTime(start.getTime() - 86400 * 1000 * 2)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '过去三天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    end.setTime(end.getTime() - 86400 * 1000 * 1)
    start.setTime(start.getTime() - 86400 * 1000 * 3)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '近七天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    start.setTime(start.getTime() - 86400 * 1000 * 6)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '过去七天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    end.setTime(end.getTime() - 86400 * 1000 * 1)
    start.setTime(start.getTime() - 86400 * 1000 * 7)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '近三十天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    start.setTime(end.getTime() - 86400 * 1000 * 29)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '过去三十天',
  onClick(picker) {
    const end = new Date()
    const start = new Date()
    end.setTime(end.getTime() - 86400 * 1000 * 1)
    start.setTime(start.getTime() - 86400 * 1000 * 30)
    picker.$emit('pick', [start, end])
  }
}, {
  text: '本月',
  onClick(picker) {
    var now = new Date()
    var year = now.getFullYear()
    var month = now.getMonth() + 1
    if (month < 10) {
      month = '0' + month
    }
    // 本月第一天
    const startDate = new Date(year + '-' + month + '-01 00:00:00')
    picker.$emit('pick', [startDate, now])
  }
}, {
  text: '上月',
  onClick(picker) {
    var now = new Date()
    var year = now.getFullYear()
    var month = now.getMonth()
    if (month == 0) {
      month = 12
      year = year - 1
    }
    if (month < 10) {
      month = '0' + month
    }

    const myDate = new Date(year, month, 0)

    // 上个月第一天
    const startDate = new Date(year + '-' + month + '-01 00:00:00')
    // 上个月最后一天
    const endDate = new Date(year + '-' + month + '-' + myDate.getDate() + ' 23:59:00')

    picker.$emit('pick', [startDate, endDate])
  }
}
]
