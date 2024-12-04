<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users and Their Transactions</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f4f4f4;
            text-align: left;
        }
    </style>
</head>
<body>
    <h1>Users and Their Transactions</h1>
    <table>
        <thead>
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Transactions</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
            <tr>
                <td>{{ $user->referral_code }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @if($user->transactions->isNotEmpty())
                        <ul>
                            @foreach ($user->transactions as $transaction)
                                <li>
                                    <strong>Description:</strong> {{ $transaction->description }}<br>
                                    <strong>From:</strong> {{ $transaction->commissionFrom->name ?? 'Unknown' }}<br>
                                    <strong>Amount:</strong> {{ number_format($transaction->amount, 2) }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        No transactions
                    @endif
                </td>
                <td>
                    {{ number_format($user->transactions->sum('amount'), 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
