<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Salary Slip</title>

<style>
body{
    font-family: DejaVu Sans;
    font-size:12px;
    color:#333;
}

.header{
    text-align:center;
    border-bottom:2px solid #000;
    margin-bottom:20px;
    padding-bottom:10px;
}

.company-name{
    font-size:22px;
    font-weight:bold;
}

.salary-title{
    font-size:16px;
    margin-top:5px;
}

.employee-table{
    width:100%;
    margin-bottom:20px;
}

.employee-table td{
    padding:5px;
}

.table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.table th{
    background:#f2f2f2;
    border:1px solid #000;
    padding:8px;
}

.table td{
    border:1px solid #000;
    padding:8px;
}

.right{
    text-align:right;
}

.total-row{
    font-weight:bold;
    background:#f9f9f9;
}

.footer{
    margin-top:40px;
}

.signature{
    width:200px;
    text-align:center;
    border-top:1px solid #000;
    padding-top:5px;
    float:right;
}
</style>
</head>

<body>

<div class="header">
    <div class="company-name">Sharnam Civil Consultancy</div>
    <div>Ahmedabad, Gujarat</div>
    <div class="salary-title">
        Salary Slip for {{ date('F', mktime(0,0,0,$salaryMonth,10)) }} {{ $salaryYear }}
    </div>
</div>


<table class="employee-table">
<tr>
<td><strong>Employee Name:</strong> {{ $employee->employee_name }}</td>
<td><strong>Employee ID:</strong> {{ $employee->employee_id }}</td>
</tr>

<tr>
<td><strong>Basic Salary:</strong> ₹ {{ number_format($employee->basic_salary,2) }}</td>
<td><strong>Paid Date:</strong> {{ optional($rows[0]->paid_date)->format('d-m-Y') }}</td>
</tr>
</table>


<table class="table">

<tr>
<th>Earnings</th>
<th class="right">Amount (₹)</th>
<th>Deductions</th>
<th class="right">Amount (₹)</th>
</tr>

<tr>
<td>Basic Salary</td>
<td class="right">{{ number_format($rows[0]->amount,2) }}</td>

<td>General Deduction</td>
<td class="right">{{ number_format($rows[0]->deduct_amount,2) }}</td>
</tr>

<tr>
<td>Leave Allowance</td>
<td class="right">0.00</td>

<td>Leave Deduction</td>
<td class="right">{{ number_format($rows[0]->leave_deduct_amount ?? 0,2) }}</td>
</tr>

<tr class="total-row">
<td>Total Earnings</td>
<td class="right">{{ number_format($rows[0]->amount,2) }}</td>

<td>Total Deduction</td>
<td class="right">
{{ number_format(($rows[0]->deduct_amount + ($rows[0]->leave_deduct_amount ?? 0)),2) }}
</td>
</tr>

<tr class="total-row">
<td colspan="3">Net Salary Paid</td>
<td class="right">
₹ {{ number_format($rows[0]->paid_amount,2) }}
</td>
</tr>

</table>


<br><br>

<table class="table">
<tr>
<th colspan="2">Leave Summary</th>
</tr>

<tr>
<td>Full Day Leave</td>
<td class="right">{{ $rows[0]->full_day_leave ?? 0 }}</td>
</tr>

<tr>
<td>Half Day Leave</td>
<td class="right">{{ $rows[0]->half_day_leave ?? 0 }}</td>
</tr>

</table>


<div class="footer">

<div class="signature">
Authorized Signature
</div>

</div>

</body>
</html>