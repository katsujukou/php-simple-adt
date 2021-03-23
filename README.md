# PHP Simple ADT
A simple *algebraic data types* (a.k.a. ADT) library based on PHP's subtyping polymorphism.

## Install
```
composer require katsujukou/php-simple-adt
```

## Usages
You can define ADTs by simply declaring your data as abstract class extending `\SimpleADT\ADT`, and define each data constructor as static method.

For example, You can define ADT which models different shapes of geometric objects like this:

```php
abstract class Point extends \SimpleADT\ADT {
    public static function Point (float $x, float $y) {
        return self::create($x, $y);
    }
}

abstract class Shape extends \SimpleADT\ADT {
    public static function Circle(Point $center, float $radius) :Shape {
        return self::create($center, $radius);
    }
    public static function Rectangle(Point $leftTop, Point $rightBottom) :Shape {
        return self::create($leftTop, $rightBottom);
    }
    public static function Triangle (Point $p1, Point $p2, Point $p3) :Shape {
        return self::create($p1, $p2, $p3);
    }
}
