<?php

namespace App\ErrorTemplate;

class ErrorTemplate
{
    public const BUY_FREE_COURSE_TEXT = 'Курс с типом "Бесплатный" и так доступен.';

    public const NOT_ENOUGH_FOR_RENT_TEXT = 'У вас не достаточно средств на счёте для аренды этого курса.';

    public const NOT_ENOUGH_FOR_BUY_TEXT = 'У вас не достаточно средств на счёте для покупки этого курса.';

    public const FREE_WITH_PRICE_TEXT = 'Курс с типом "Бесплатный" не может иметь стоимость отличной от нуля.';

    public const COSTABLE_WITH_ZERO_COST_TEXT = 'Курс с типом "Аренда" или "Покупка" не может'
    . 'нулевую или отрицательную стоимость.';

    public const WRONG_TYPE_TEXT = 'Поле "Тип" не должно иметь значение: "rent", "buy" или "free".';

    public const COURSE_DOESNT_EXIST_TEXT = "Курс с таким символьным кодом не найден.";

    public const COURSE_EXIST_TEXT = 'Курс с таким символьным кодом уже существует.';

    public const USER_UNAUTH_TEXT = 'Вы не авторизованы.';

    public const ACCESS_RIGHT_TEXT = "К этому запросу имеет доступ только пользователь с правами администратора.";

    public const NOT_UNIQUE_EMAIL_TEXT = 'Пользователь с таким E-mail уже зарегистрирован.';

    public const PURCHASED_COURSE_TEXT = 'Этот курс уже куплен.';

    public const RENTED_COURSE_TEXT = 'Этот курс уже арендован и длительность аренды ещё не истекла.';
}
