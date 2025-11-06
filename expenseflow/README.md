## 📊 ExpenseFlow – Online Income & Expense Tracker

**ExpenseFlow** is a simple yet powerful web application designed to help users track and analyze their daily financial activities.
It allows multiple users to manage **income, expenses, and cash/bank balances** with real-time reporting and visual analytics.

### 🧩 Key Features

1. **User Accounts**

   * Secure login and registration with email and password.
   * Each user can select their preferred currency (USD, SAR, EGP, INR).

2. **Transactions**

   * Add new **income** or **expense** records with:

     * Date
     * Type (Income / Expense)
     * Payment Method (Cash / Bank)
     * Amount
     * Category
     * Description / Notes
   * Supports *soft delete* — deleted transactions remain visible with a “Deleted” label.

3. **Reports & Analytics**

   * View summaries for **Today, Current Week, Current Month, and Current Year**.
   * Filter and review past transactions through the **Report History** page.
   * Export filtered data as **CSV** files.
   * Visual charts (Pie / Bar) on the **Analysis** page showing spending by category.

4. **Settings**

   * Change default currency.
   * Manage categories (Add / Delete).
   * Reset the entire system (requires triple confirmation and typing “delete”).

5. **Multi-User Support**

   * Each user has an isolated account and data.
   * Multiple users can access the system online simultaneously.

6. **Technology Stack**

   * **Backend:** PHP + MySQL
   * **Frontend:** HTML, CSS, JavaScript
   * **Visualization:** Chart.js
   * **Responsive Design:** Mobile-friendly and simple dashboard layout